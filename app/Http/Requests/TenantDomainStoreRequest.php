<?php

namespace App\Http\Requests;

use App\Support\HostnameNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantDomainStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hostname' => ['required', 'string', 'max:253', Rule::unique('tenant_domains', 'hostname'), function (string $attribute, mixed $value, \Closure $fail): void {
                try {
                    HostnameNormalizer::normalize((string) $value);
                } catch (\InvalidArgumentException) {
                    $fail('Informe um hostname válido, como gestao.cliente.com.');
                }
            }],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['hostname' => strtolower(trim((string) $this->input('hostname')))]);
    }
}
