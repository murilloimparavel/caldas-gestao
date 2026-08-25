<?php

namespace App\Support;

use Illuminate\Support\Arr;
use JsonException;

final class PayloadGovernance
{
    /** @var list<string> */
    private const FORBIDDEN_KEY_FRAGMENTS = [
        'password', 'secret', 'token', 'credential', 'authorization', 'cookie',
        'email', 'phone', 'address', 'document', 'cpf', 'cnpj', 'name', 'ip',
        'user_agent', 'session', 'api_key', 'private_key', 'access_key',
    ];

    /** @var list<string> */
    private const AUDIT_KEYS = [
        'lock_version', 'scope_kind', 'unit_id', 'role_id', 'membership_id',
        'resource_id', 'resource_type', 'status', 'reason_code', 'quantity',
    ];

    /** @var list<string> */
    private const EVENT_KEYS = [
        'resource_id', 'resource_type', 'tenant_id', 'unit_id', 'membership_id',
        'role_id', 'status', 'scope_kind', 'lock_version', 'quantity', 'key',
    ];

    /** @var list<string> */
    private const RESPONSE_KEYS = [
        'resource_id', 'resource_type', 'status', 'response_code', 'next_cursor',
    ];

    /** @var list<string> */
    private const ENTITLEMENT_KEYS = [
        'feature', 'label', 'description', 'limit', 'unit', 'metadata',
    ];

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function auditMetadata(array $payload): array
    {
        return $this->allow($payload, self::AUDIT_KEYS, 'audit metadata');
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function eventPayload(array $payload): array
    {
        return $this->allow($payload, self::EVENT_KEYS, 'event payload');
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function responseReference(array $payload): array
    {
        return $this->allow($payload, self::RESPONSE_KEYS, 'idempotency response reference');
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function entitlementConfig(array $payload): array
    {
        return $this->allow($payload, self::ENTITLEMENT_KEYS, 'entitlement config');
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function entitlementMetadata(array $payload): array
    {
        return Arr::only($this->allow($payload, self::ENTITLEMENT_KEYS, 'entitlement metadata'), ['feature', 'label', 'description', 'limit', 'unit']);
    }

    public function canonicalHash(mixed $value): string
    {
        try {
            return hash('sha256', json_encode($this->canonicalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('The payload must be JSON serializable.', 0, $exception);
        }
    }

    /** @param array<string, mixed> $payload
     * @param  list<string>  $allowedKeys
     * @return array<string, mixed>
     */
    private function allow(array $payload, array $allowedKeys, string $field): array
    {
        $normalized = [];

        foreach ($payload as $key => $value) {
            if ($this->isForbidden($key)) {
                throw new \InvalidArgumentException("The {$field} contains a forbidden key.");
            }

            if (! in_array($key, $allowedKeys, true)) {
                throw new \InvalidArgumentException("The {$field} key [{$key}] is not allowed.");
            }

            $normalized[$key] = $this->value($value, $field, $key, $this->nestedAllowedKeys($field, $key, $allowedKeys));
        }

        return $normalized;
    }

    private function isForbidden(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::FORBIDDEN_KEY_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $allowedKeys */
    private function value(mixed $value, string $field, string $key, array $allowedKeys): mixed
    {
        if (is_object($value) || is_resource($value)) {
            throw new \InvalidArgumentException("The {$field} key [{$key}] cannot contain an object or resource.");
        }

        if (is_array($value)) {
            $nested = [];
            $isList = array_is_list($value);

            foreach ($value as $nestedKey => $nestedValue) {
                if (! $isList && (! is_string($nestedKey) || $this->isForbidden($nestedKey) || ! in_array($nestedKey, $allowedKeys, true))) {
                    throw new \InvalidArgumentException("The {$field} key [{$key}] contains a forbidden nested key.");
                }

                $nested[$nestedKey] = $this->value($nestedValue, $field, $key, $allowedKeys);
            }

            return $nested;
        }

        if (! is_scalar($value) && $value !== null) {
            throw new \InvalidArgumentException("The {$field} key [{$key}] contains an unsupported value.");
        }

        return $value;
    }

    /** @param list<string> $allowedKeys
     * @return list<string>
     */
    private function nestedAllowedKeys(string $field, string $key, array $allowedKeys): array
    {
        if (str_contains($field, 'entitlement') && $key === 'metadata') {
            return ['feature', 'label', 'description', 'limit', 'unit'];
        }

        return $allowedKeys;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->canonicalize($item);
            }

            if (! array_is_list($value)) {
                ksort($normalized, SORT_STRING);
            }

            return $normalized;
        }

        if (is_object($value) || is_resource($value)) {
            throw new \InvalidArgumentException('The payload must contain JSON arrays and scalar values only.');
        }

        return $value;
    }
}
