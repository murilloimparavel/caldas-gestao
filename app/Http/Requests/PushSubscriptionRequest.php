<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'url:https', 'max:2048', function (string $attribute, mixed $value, Closure $fail): void {
                $url = parse_url($value);
                $host = strtolower($url['host'] ?? '');
                $allowed = in_array($host, ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'], true)
                    || preg_match('/^[a-z0-9-]+(?:\.[a-z0-9-]+)*\.push\.apple\.com$/D', $host) === 1
                    || preg_match('/^[a-z0-9-]+\.notify\.windows\.com$/D', $host) === 1;
                if (! $allowed || isset($url['user']) || isset($url['pass']) || isset($url['port']) || isset($url['fragment'])) {
                    $fail('Use uma inscrição de um serviço de notificações compatível.');
                }
            }],
            'keys' => ['required', 'array:p256dh,auth'],
            'keys.p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{87}={0,2}$/D', function (string $attribute, mixed $value, Closure $fail): void {
                $decoded = base64_decode(strtr($value, '-_', '+/'), true);
                if ($decoded === false || strlen($decoded) !== 65 || ord($decoded[0]) !== 4) {
                    $fail('A chave pública do aparelho é inválida.');
                }
            }],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{22}={0,2}$/D'],
            'contentEncoding' => ['sometimes', Rule::in(['aes128gcm', 'aesgcm'])],
            'device_name' => ['required', 'string', 'max:80'],
        ];
    }
}
