<?php

namespace App\Http\Requests;

use App\Models\CustomerSubscription;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ConsumeSubscriptionUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscription = $this->route('customer_subscription') ?? $this->route('customerSubscription');

        return $subscription instanceof CustomerSubscription
            ? Gate::allows('consumeUsage', $subscription)
            : Gate::allows('consumeUsage', CustomerSubscription::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'uuid'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
