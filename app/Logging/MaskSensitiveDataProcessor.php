<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class MaskSensitiveDataProcessor implements ProcessorInterface
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'cpf',
        'phone',
        'password',
        'credit_card',
        'card_number',
        'cvv',
        'secret',
        'token',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->maskArray($record->context);
        $extra = $this->maskArray($record->extra);
        $message = $this->maskString($record->message);

        return $record->with(
            message: $message,
            context: $context,
            extra: $extra
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $data[$key] = '********';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskArray($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->maskString($value);
            }
        }

        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    private function maskString(string $value): string
    {
        return preg_replace(
            '/((?:"?(?:cpf|phone|password|credit_card|card_number|cvv|secret|token)"?\s*[:=]\s*)"?)([^"&\s,]+)("?)/i',
            '${1}********${3}',
            $value
        ) ?? $value;
    }
}
