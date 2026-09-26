<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'preset' => ['nullable', 'string', 'in:today,7d,30d,this_month,custom'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'required_if:preset,custom'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('preset') !== 'custom'
                || ! $this->input('start_date')
                || ! $this->input('end_date')
                || $validator->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }

            if (CarbonImmutable::parse($this->input('start_date'))->diffInDays(CarbonImmutable::parse($this->input('end_date'))) > 365) {
                $validator->errors()->add('end_date', 'O período personalizado não pode exceder 366 dias.');
            }
        }];
    }
}
