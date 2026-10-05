<?php

namespace App\Support\Assistant;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class GroqChatClient
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function complete(array $messages, array $tools): array
    {
        $apiKey = config('assistant.groq.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new AssistantUnavailableException('O assistente ainda não está configurado.');
        }

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->withToken($apiKey)
                ->retry([200, 500], 0, function (Throwable $exception, PendingRequest $request, ?string $method): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && ($exception->response->tooManyRequests()
                                || $exception->response->serverError()));
                })
                ->timeout((int) config('assistant.groq.timeout', 20))
                ->connectTimeout((int) config('assistant.groq.connect_timeout', 5))
                ->post((string) config('assistant.groq.endpoint'), [
                    'model' => (string) config('assistant.groq.model'),
                    'messages' => $messages,
                    'tools' => $tools,
                    'tool_choice' => 'auto',
                ])
                ->throw();
        } catch (Throwable $exception) {
            throw new AssistantUnavailableException('O provedor de IA está temporariamente indisponível.', 0, $exception);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! is_array($payload['choices'] ?? null) || ! isset($payload['choices'][0]['message'])) {
            throw new AssistantUnavailableException('O provedor de IA retornou uma resposta inválida.');
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }
}
