<?php

namespace App\Support\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Policies\IntegrationAdminPolicy;
use App\Support\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Throwable;

final class AssistantChatService
{
    private const int MAX_TOOL_ROUNDS = 3;

    public function __construct(
        private readonly GroqChatClient $client,
        private readonly AssistantToolExecutor $tools,
        private readonly AssistantSensitiveContentRedactor $redactor,
        private readonly IntegrationAdminPolicy $integrationAdmin,
    ) {}

    public function createConversation(User $user, TenantContext $context): AssistantConversation
    {
        $this->assertAdministrator($user, $context);

        return AssistantConversation::query()->create([
            'user_id' => $user->getKey(),
            'tenant_id' => $context->tenant->getKey(),
            'unit_id' => $context->unit?->getKey(),
            'title' => null,
            'expires_at' => now()->addDays($this->retentionDays()),
        ]);
    }

    public function assertConversation(User $user, TenantContext $context, AssistantConversation $conversation): void
    {
        $this->assertAdministrator($user, $context);

        if ($conversation->user_id !== (string) $user->getKey()
            || $conversation->tenant_id !== (string) $context->tenant->getKey()
            || $conversation->unit_id !== (string) $context->unit?->getKey()
            || ! $this->timestamp($conversation->getAttribute('expires_at'))->isFuture()) {
            throw new AuthorizationException('A conversa não pertence ao contexto administrativo atual.');
        }
    }

    /**
     * @return array{redacted: bool, user_message_id: string, assistant_message_id: string, proposal_ids: list<string>}
     */
    public function send(User $user, TenantContext $context, AssistantConversation $conversation, string $content): array
    {
        $this->assertConversation($user, $context, $conversation);

        $content = trim($content);
        $redactedContent = $this->redactor->redact($content);
        $wasRedacted = $redactedContent !== $content;

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $redactedContent,
            'metadata' => $wasRedacted ? ['redacted' => true] : null,
        ]);

        $conversation->last_message_at = now();
        $conversation->expires_at = now()->addDays($this->retentionDays());
        $conversation->save();

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ...$this->history($conversation),
        ];
        $proposalIds = [];
        $catalogHasMoreByKind = [];
        $assistantMessageId = '';
        $completed = false;

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $response = $this->client->complete($messages, $this->tools->definitions());
            $message = $this->providerMessage($response);
            $assistantContent = $this->redactor->redact($this->stringValue($message['content'] ?? ''));
            $toolCalls = $this->toolCalls($message['tool_calls'] ?? null);
            $safeToolCalls = $this->sanitizeToolCalls($toolCalls);
            if ($safeToolCalls === [] && in_array(true, $catalogHasMoreByKind, true)) {
                $partialNotice = 'A leitura do catálogo ficou parcial. Há páginas seguintes disponíveis; não trate esta lista como completa.';
                $assistantContent = trim($assistantContent) === ''
                    ? $partialNotice
                    : trim($assistantContent).' '.$partialNotice;
            }

            $assistantMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $assistantContent,
                'metadata' => $safeToolCalls === [] ? null : ['tool_calls' => $safeToolCalls],
            ]);
            $assistantMessageId = (string) $assistantMessage->getKey();

            $assistantPayload = [
                'role' => 'assistant',
                'content' => $assistantContent,
            ];

            if ($safeToolCalls === []) {
                $completed = true;
                break;
            }

            $assistantPayload['tool_calls'] = $safeToolCalls;
            $messages[] = $assistantPayload;

            foreach ($safeToolCalls as $toolCall) {
                $idempotencyKey = implode(':', [
                    'conversation',
                    (string) $conversation->getKey(),
                    'message',
                    (string) $userMessage->getKey(),
                    'tool',
                    $toolCall['id'],
                ]);
                $toolResult = $this->executeToolCall($toolCall, $user, $context, $idempotencyKey);
                $proposalId = $toolResult['proposal_id'] ?? null;
                unset($toolResult['proposal_id']);
                if (is_string($proposalId)) {
                    $proposalIds[] = $proposalId;
                }
                if ($toolCall['function']['name'] === 'read_catalog') {
                    $arguments = json_decode($toolCall['function']['arguments'], true);
                    $kind = is_array($arguments) && is_string($arguments['kind'] ?? null)
                        ? $arguments['kind']
                        : 'services';
                    $catalogHasMoreByKind[$kind] = (bool) ($toolResult['pagination']['has_more'] ?? false);
                }
                $toolContent = $this->redactor->redact($this->encode($toolResult));
                $conversation->messages()->create([
                    'role' => 'tool',
                    'content' => $toolContent,
                    'metadata' => [
                        'tool_call_id' => $toolCall['id'],
                        'name' => $toolCall['function']['name'],
                    ],
                ]);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'name' => $toolCall['function']['name'],
                    'content' => $toolContent,
                ];

            }
        }

        if (! $completed && $assistantMessageId !== '') {
            $conversation->messages()->whereKey($assistantMessageId)->update([
                'content' => in_array(true, $catalogHasMoreByKind, true)
                    ? 'Cheguei ao limite de etapas desta solicitação antes de terminar a leitura do catálogo. A lista apresentada é parcial. Envie uma nova mensagem para continuar.'
                    : 'Cheguei ao limite de etapas desta solicitação. Revise as propostas pendentes e envie uma nova mensagem para continuar.',
            ]);
        }

        $conversation->last_message_at = now();
        $conversation->expires_at = now()->addDays($this->retentionDays());
        $conversation->save();

        return [
            'redacted' => $wasRedacted,
            'user_message_id' => (string) $userMessage->getKey(),
            'assistant_message_id' => $assistantMessageId,
            'proposal_ids' => array_values(array_unique($proposalIds)),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function visibleMessages(AssistantConversation $conversation): array
    {
        return $conversation->messages()
            ->where(function (Builder $query): void {
                $query->where('role', 'user')
                    ->orWhere(fn (Builder $assistant): Builder => $assistant
                        ->where('role', 'assistant')
                        ->where('content', '<>', ''));
            })
            ->latest('created_at')
            ->latest('id')
            ->limit((int) config('assistant.max_history_messages', 20))
            ->get()
            ->reverse()
            ->map(fn (AssistantMessage $message): array => [
                'id' => (string) $message->getKey(),
                'role' => $message->role,
                'content' => $message->content,
                'createdAt' => $message->created_at?->toISOString(),
                'redacted' => (bool) ($this->metadata($message)['redacted'] ?? false),
            ])
            ->pipe(fn ($messages): array => array_values($messages->all()));
    }

    private function assertAdministrator(User $user, TenantContext $context): void
    {
        if (! $context->user->is($user)
            || $context->unit === null
            || ! $this->integrationAdmin->allows($user, $context)) {
            throw new AuthorizationException('O assistente requer um administrador elegível com unidade ativa.');
        }
    }

    /** @return list<array<string, mixed>> */
    private function history(AssistantConversation $conversation): array
    {
        return $conversation->messages()
            ->latest('created_at')
            ->latest('id')
            ->limit((int) config('assistant.max_history_messages', 20))
            ->get()
            ->reverse()
            ->map(function (AssistantMessage $message): array {
                $metadata = $this->metadata($message);
                $payload = [
                    'role' => $message->role,
                    'content' => $message->content,
                ];

                if ($message->role === 'assistant' && isset($metadata['tool_calls'])) {
                    $payload['tool_calls'] = $metadata['tool_calls'];
                }

                if ($message->role === 'tool') {
                    $payload['tool_call_id'] = (string) ($metadata['tool_call_id'] ?? '');
                    $payload['name'] = (string) ($metadata['name'] ?? '');
                }

                return $payload;
            })
            ->pipe(fn ($messages): array => array_values($messages->all()));
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function providerMessage(array $response): array
    {
        $message = $response['choices'][0]['message'] ?? null;

        if (! is_array($message)) {
            throw new AssistantUnavailableException('O provedor de IA retornou uma resposta inválida.');
        }

        /** @var array<string, mixed> $message */
        return $message;
    }

    /** @return list<array{id: string, type: string, function: array{name: string, arguments: string}}> */
    private function toolCalls(mixed $toolCalls): array
    {
        if (! is_array($toolCalls)) {
            return [];
        }

        $calls = [];
        foreach ($toolCalls as $index => $toolCall) {
            if (! is_array($toolCall)) {
                continue;
            }

            $function = $toolCall['function'] ?? null;
            if (! is_array($function) || ! is_string($function['name'] ?? null)) {
                continue;
            }

            $arguments = $function['arguments'] ?? '{}';
            if (is_array($arguments)) {
                $arguments = $this->encode($arguments);
            }

            $calls[] = [
                'id' => is_string($toolCall['id'] ?? null) && $toolCall['id'] !== ''
                    ? $toolCall['id']
                    : 'call-'.(string) $index,
                'type' => 'function',
                'function' => [
                    'name' => $function['name'],
                    'arguments' => is_string($arguments) ? $arguments : '{}',
                ],
            ];
        }

        return $calls;
    }

    /**
     * @param  array{id: string, type: string, function: array{name: string, arguments: string}}  $toolCall
     * @return array<string, mixed>
     */
    private function executeToolCall(array $toolCall, User $user, TenantContext $context, string $idempotencyKey): array
    {
        $arguments = json_decode($toolCall['function']['arguments'], true);
        if (! is_array($arguments)) {
            return ['error' => 'Os argumentos da ferramenta são inválidos.'];
        }

        if (isset($arguments['_assistant_rejected'])) {
            return [
                'error' => match ($arguments['_assistant_rejected']) {
                    'sensitive_content' => 'Os argumentos da ferramenta foram rejeitados por conter dados sensíveis.',
                    'unsupported_arguments' => 'Os argumentos da ferramenta não são permitidos.',
                    default => 'Os argumentos da ferramenta são inválidos.',
                },
            ];
        }

        try {
            return $this->tools->execute($toolCall['function']['name'], $arguments, $user, $context, $idempotencyKey);
        } catch (Throwable) {
            return ['error' => 'Não foi possível concluir essa ferramenta no contexto atual.'];
        }
    }

    private function systemPrompt(): string
    {
        return 'Você é o assistente administrativo do sistema Caldas Gestão. '
            .'Atue somente no contexto de tenant e unidade vinculados a esta conversa; o servidor aplica esse escopo. '
            .'Você pode ler apenas nomes de categorias, serviços e profissionais, preço e duração de serviços e indicadores agregados de setup. '
            .'Ao ler o catálogo, verifique has_more. Se for true, solicite páginas seguintes conforme necessário e nunca apresente uma página como se fosse o catálogo completo. Se o limite de etapas impedir concluir a leitura, informe que a lista é parcial. '
            .'Pode criar propostas para category.create, category.update, professional.create, professional.update, service.create, service.update e unit.update. '
            .'Uma proposta nunca executa a alteração: explique a mudança e peça revisão humana na tela, que exigirá passkey. '
            .'Não solicite, leia, armazene ou processe clientes, contatos, agenda, vendas, caixa, estoque, notas, e-mails, telefones ou documentos. '
            .'Se o usuário fornecer dados pessoais, ignore-os e informe que eles não são aceitos.';
    }

    private function retentionDays(): int
    {
        return max(1, (int) config('assistant.retention_days', 30));
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * Decode and validate tool arguments before storage or replay. Regex-redacting
     * the raw JSON could change UUIDs or other valid values.
     *
     * @param  list<array{id: string, type: string, function: array{name: string, arguments: string}}>  $toolCalls
     * @return list<array{id: string, type: string, function: array{name: string, arguments: string}}>
     */
    private function sanitizeToolCalls(array $toolCalls): array
    {
        $ids = [];

        return array_map(function (array $toolCall, int $index) use (&$ids): array {
            $id = $this->safeToolCallId($toolCall['id'], $index, $ids);
            $ids[$id] = true;
            $name = $this->safeToolName($toolCall['function']['name']);
            $toolCall['id'] = $id;
            $toolCall['function']['name'] = $name;
            $toolCall['function']['arguments'] = $this->sanitizeToolArguments($name, $toolCall['function']['arguments']);

            return $toolCall;
        }, $toolCalls, array_keys($toolCalls));
    }

    private function sanitizeToolArguments(string $name, string $rawArguments): string
    {
        try {
            $arguments = json_decode($rawArguments, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $this->encode(['_assistant_rejected' => 'invalid_arguments']);
        }

        if (! is_array($arguments)) {
            return $this->encode(['_assistant_rejected' => 'invalid_arguments']);
        }

        if (! $this->hasAllowedArgumentShape($name, $arguments)) {
            return $this->encode(['_assistant_rejected' => 'unsupported_arguments']);
        }

        if ($this->containsSensitiveArgument($arguments)) {
            return $this->encode(['_assistant_rejected' => 'sensitive_content']);
        }

        return $this->encode($arguments);
    }

    /** @param array<string, mixed> $arguments */
    private function hasAllowedArgumentShape(string $name, array $arguments): bool
    {
        $allowed = match ($name) {
            'read_catalog' => ['kind', 'page', 'per_page'],
            'read_setup_status' => [],
            'propose_operation' => ['operation', 'input'],
            default => array_keys($arguments),
        };

        if (array_diff(array_keys($arguments), $allowed) !== []) {
            return false;
        }

        if ($name !== 'propose_operation') {
            return true;
        }

        $operation = $arguments['operation'] ?? null;
        $input = $arguments['input'] ?? null;
        if (! is_string($operation) || ! is_array($input)) {
            return false;
        }

        $allowedInput = match ($operation) {
            'category.create' => ['name', 'type', 'is_active'],
            'category.update' => ['category_name', 'name', 'type', 'is_active'],
            'professional.create' => ['name', 'status', 'service_names'],
            'professional.update' => ['professional_name', 'name', 'status', 'service_names'],
            'service.create' => ['name', 'duration_minutes', 'price_cents', 'status', 'professional_names', 'category_name'],
            'service.update' => ['service_name', 'name', 'duration_minutes', 'price_cents', 'status', 'category_name', 'professional_names'],
            'unit.update' => ['name', 'timezone', 'online_booking_enabled', 'appointment_sales_automation_enabled'],
            default => null,
        };

        return $allowedInput !== null && array_diff(array_keys($input), $allowedInput) === [];
    }

    /** @param array<string, mixed> $arguments */
    private function containsSensitiveArgument(array $arguments): bool
    {
        return $this->containsSensitiveValue('', $arguments);
    }

    private function containsSensitiveValue(string $path, mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                if ($this->containsSensitiveValue($path.'.'.(string) $key, $child)) {
                    return true;
                }
            }

            return false;
        }

        if (! is_string($value) || $this->isNonFreeTextArgument($path)) {
            return false;
        }

        return $this->redactor->redact($value) !== $value;
    }

    private function isNonFreeTextArgument(string $path): bool
    {
        $field = str($path)->afterLast('.')->toString();

        return in_array($field, [
            'kind', 'operation', 'type', 'status', 'timezone', 'state', 'category_id', 'service_id',
            'professional_id', 'professional_ids', 'expected_version', 'duration_minutes', 'price_cents',
            'is_active', 'online_booking_enabled', 'appointment_sales_automation_enabled',
        ], true);
    }

    /** @param array<string, bool> $ids */
    private function safeToolCallId(string $id, int $index, array $ids): string
    {
        $candidate = preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.:-]{0,127}\z/', $id) === 1
            ? $id
            : 'call-'.$index;

        return isset($ids[$candidate]) ? $candidate.'-'.$index : $candidate;
    }

    private function safeToolName(string $name): string
    {
        return preg_match('/\A[a-zA-Z0-9_]{1,64}\z/', $name) === 1 ? $name : 'unauthorized_tool';
    }

    /** @return array<string, mixed> */
    private function metadata(AssistantMessage $message): array
    {
        $metadata = $message->getAttribute('metadata');

        return is_array($metadata) ? $metadata : [];
    }

    private function timestamp(mixed $value): CarbonInterface
    {
        if (! $value instanceof CarbonInterface) {
            throw new LogicException('A data de expiração da conversa não está disponível.');
        }

        return $value;
    }
}
