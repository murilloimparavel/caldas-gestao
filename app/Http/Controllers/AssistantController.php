<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssistantMessageRequest;
use App\Models\AssistantConversation;
use App\Models\User;
use App\Support\Assistant\AssistantChatService;
use App\Support\Assistant\AssistantUnavailableException;
use App\Support\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class AssistantController extends Controller
{
    public function index(Request $request, TenantContext $context, AssistantChatService $assistant): Response
    {
        $user = $this->user($request);
        $conversations = $this->conversations($user, $context);

        return $this->render($conversations->first(), $conversations, $context, $assistant);
    }

    public function show(Request $request, TenantContext $context, AssistantConversation $conversation, AssistantChatService $assistant): Response
    {
        $user = $this->user($request);
        $assistant->assertConversation($user, $context, $conversation);

        return $this->render($conversation, $this->conversations($user, $context), $context, $assistant);
    }

    public function storeConversation(Request $request, TenantContext $context, AssistantChatService $assistant): RedirectResponse
    {
        $conversation = $assistant->createConversation($this->user($request), $context);

        return to_route('assistant.show', $conversation);
    }

    public function send(AssistantMessageRequest $request, TenantContext $context, AssistantConversation $conversation, AssistantChatService $assistant): RedirectResponse
    {
        $user = $this->user($request);

        try {
            $result = $assistant->send($user, $context, $conversation, $request->content());
        } catch (AssistantUnavailableException $exception) {
            return to_route('assistant.show', $conversation)->with('error', $exception->getMessage());
        }

        $redirect = to_route('assistant.show', $conversation);
        if ($result['redacted']) {
            $redirect->with('warning', 'Dados parecidos com e-mail, telefone ou documento foram ocultados antes do envio.');
        }
        if ($result['proposal_ids'] !== []) {
            $redirect->with('proposal_ids', $result['proposal_ids']);
        }

        return $redirect;
    }

    public function destroy(Request $request, TenantContext $context, AssistantConversation $conversation, AssistantChatService $assistant): RedirectResponse
    {
        $user = $this->user($request);
        $assistant->assertConversation($user, $context, $conversation);
        $conversation->delete();

        return to_route('assistant.index')->with('success', 'Conversa excluída.');
    }

    /** @return Collection<int, AssistantConversation> */
    private function conversations(User $user, TenantContext $context): Collection
    {
        return AssistantConversation::query()
            ->where('user_id', $user->getKey())
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->where('expires_at', '>', now())
            ->latest('last_message_at')
            ->latest('created_at')
            ->limit(30)
            ->get();
    }

    /** @param Collection<int, AssistantConversation> $conversations */
    private function render(?AssistantConversation $conversation, Collection $conversations, TenantContext $context, AssistantChatService $assistant): Response
    {
        return Inertia::render('assistant/index', [
            'conversation' => $conversation === null ? null : [
                'id' => (string) $conversation->getKey(),
                'title' => $conversation->title,
                'expiresAt' => $this->timestamp($conversation->getAttribute('expires_at'))->toISOString(),
                'messages' => $assistant->visibleMessages($conversation),
            ],
            'conversations' => array_values($conversations->map(fn (AssistantConversation $item): array => [
                'id' => (string) $item->getKey(),
                'title' => $item->title ?: 'Nova conversa',
                'lastMessageAt' => $this->nullableTimestamp($item->getAttribute('last_message_at'))?->toISOString()
                    ?? $this->nullableTimestamp($item->getAttribute('created_at'))?->toISOString(),
            ])->all()),
            'workspace' => [
                'tenantName' => $context->tenant->name,
                'unitName' => $context->unit?->name,
            ],
            'limits' => [
                'maxPromptLength' => (int) config('assistant.max_prompt_length', 4000),
                'retentionDays' => max(1, (int) config('assistant.retention_days', 30)),
                'available' => filled(config('assistant.groq.api_key')),
            ],
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        Gate::authorize('manage-integrations');

        return $user;
    }

    private function timestamp(mixed $value): CarbonInterface
    {
        if (! $value instanceof CarbonInterface) {
            throw new LogicException('A data de expiração da conversa não está disponível.');
        }

        return $value;
    }

    private function nullableTimestamp(mixed $value): ?CarbonInterface
    {
        if ($value === null) {
            return null;
        }

        return $this->timestamp($value);
    }
}
