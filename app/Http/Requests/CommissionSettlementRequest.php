<?php

namespace App\Http\Requests;

use App\Models\CommissionRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class CommissionSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('settle', CommissionRule::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'professional_id' => ['required', 'uuid', 'exists:professionals,id'],
            'accrual_ids' => ['nullable', 'array'],
            'accrual_ids.*' => ['uuid', 'exists:commission_accruals,id'],
            'paid_at' => ['nullable', 'date'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
