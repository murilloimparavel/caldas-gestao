<?php

namespace App\Support;

use Illuminate\Support\Arr;

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
        'price_cents', 'duration_minutes', 'service_ids', 'professional_ids',
        'category_id', 'is_active', 'cost_price_cents', 'sale_price_cents',
        'current_stock', 'min_stock', 'unit_of_measure',
        'type', 'uniqueness_scope', 'key', 'sale_category_id', 'customer_id',
        'service_id', 'product_id', 'professional_id', 'appointment_id', 'sale_id', 'sale_item_id', 'reference_label',
        'open_context_key', 'currency', 'total_amount_cents', 'discount_amount_cents',
        'final_amount_cents', 'item_type', 'unit_price_cents', 'discount_cents',
        'total_cents', 'closing_session_id', 'closing_subject', 'expected_total_cents',
        'final_total_cents', 'receipt_number', 'closed_by_user_id', 'sale_ids', 'original_payment_id', 'reversal_payment_id', 'cash_shift_adjusted',
        'from_status', 'to_status', 'reason',
        'cash_shift_id', 'cash_movement_id', 'opened_by_user_id', 'initial_amount_cents',
        'expected_amount_cents', 'difference_cents', 'amount_cents', 'reference_type', 'reference_id',
        'inventory_movement_id', 'unit_cost_cents', 'previous_stock', 'resulting_stock',
        'commission_rule_id', 'commission_settlement_id', 'commission_accrual_id', 'rate_type', 'rate_value', 'value_rate',
        'commission_amount_cents', 'gross_amount_cents', 'settlement_id', 'settled_at', 'paid_at', 'period_start', 'period_end', 'notes', 'accrual_ids',
        'financial_obligation_id', 'supplier_id', 'due_date', 'paid_date', 'payment_method',
        'package_template_id', 'customer_package_id', 'package_usage_id', 'sessions_consumed', 'remaining_sessions', 'total_sessions', 'validity_days', 'expires_at',
        'subscription_plan_id', 'customer_subscription_id', 'billing_cycle', 'start_date', 'next_billing_date', 'cancelled_at',
        'channel', 'opted_in', 'event_type', 'retention_status', 'days', 'legal_hold_id', 'anonymization_version',
    ];

    /** @var list<string> */
    private const EVENT_KEYS = [
        'resource_id', 'resource_type', 'tenant_id', 'unit_id', 'membership_id',
        'role_id', 'status', 'scope_kind', 'lock_version', 'quantity', 'key',
        'service_ids', 'professional_ids',
        'category_id', 'is_active', 'cost_price_cents', 'sale_price_cents',
        'current_stock', 'min_stock', 'unit_of_measure',
        'type', 'uniqueness_scope', 'sale_category_id', 'customer_id',
        'service_id', 'product_id', 'professional_id', 'appointment_id', 'sale_id', 'sale_item_id', 'reference_label',
        'open_context_key', 'currency', 'total_amount_cents', 'discount_amount_cents',
        'final_amount_cents', 'item_type', 'unit_price_cents', 'discount_cents',
        'total_cents', 'closing_session_id', 'closing_subject', 'expected_total_cents',
        'final_total_cents', 'receipt_number', 'closed_by_user_id', 'sale_ids', 'original_payment_id', 'reversal_payment_id', 'cash_shift_adjusted',
        'from_status', 'to_status', 'reason',
        'cash_shift_id', 'cash_movement_id', 'opened_by_user_id', 'initial_amount_cents',
        'expected_amount_cents', 'difference_cents', 'amount_cents', 'reference_type', 'reference_id',
        'inventory_movement_id', 'unit_cost_cents', 'previous_stock', 'resulting_stock',
        'commission_rule_id', 'commission_settlement_id', 'commission_accrual_id', 'rate_type', 'rate_value', 'value_rate',
        'commission_amount_cents', 'gross_amount_cents', 'settlement_id', 'settled_at', 'paid_at', 'period_start', 'period_end', 'notes', 'accrual_ids',
        'financial_obligation_id', 'supplier_id', 'due_date', 'paid_date', 'payment_method',
        'package_template_id', 'customer_package_id', 'package_usage_id', 'sessions_consumed', 'remaining_sessions', 'total_sessions', 'validity_days', 'expires_at',
        'subscription_plan_id', 'customer_subscription_id', 'billing_cycle', 'start_date', 'next_billing_date', 'cancelled_at',
        'legal_hold_id', 'anonymization_version',
    ];

    /** @var list<string> */
    private const RESPONSE_KEYS = [
        'resource_id', 'resource_type', 'status', 'response_code', 'next_cursor',
        'confirmation_status', 'confirmation_message',
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
        } catch (\JsonException $exception) {
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

        if (in_array($key, ['closing_session_id', 'closing_session', 'receipt_number', 'description', 'membership_id', 'total_sessions', 'remaining_sessions', 'sessions_consumed'], true)) {
            return false;
        }

        foreach (self::FORBIDDEN_KEY_FRAGMENTS as $fragment) {
            if ($fragment === 'ip') {
                if ($key === 'ip' || str_starts_with($key, 'ip_') || str_ends_with($key, '_ip') || str_contains($key, '_ip_')) {
                    return true;
                }

                continue;
            }

            if ($fragment === 'session') {
                if (str_contains($key, 'session') && ! str_contains($key, 'closing_session') && ! in_array($key, ['total_sessions', 'remaining_sessions', 'sessions_consumed'], true)) {
                    return true;
                }

                continue;
            }

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
