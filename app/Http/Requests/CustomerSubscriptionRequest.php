<?php

namespace App\Http\Requests;

use App\Models\CustomerSubscription;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class CustomerSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscription = $this->route('customer_subscription') ?? $this->route('customerSubscription');

        if ($subscription instanceof CustomerSubscription) {
            if ($this->routeIs('customer-subscriptions.cancel')) {
                return Gate::allows('cancel', $subscription);
            }

            if ($this->routeIs('customer-subscriptions.pause')) {
                return Gate::allows('pause', $subscription);
            }

            if ($this->routeIs('customer-subscriptions.resume')) {
                return Gate::allows('resume', $subscription);
            }

            return Gate::allows('view', $subscription);
        }

        return Gate::allows('create', CustomerSubscription::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        if ($this->routeIs('customer-subscriptions.cancel')) {
            return [
                'notes' => ['nullable', 'string', 'max:2000'],
                'lock_version' => ['required', 'integer', 'min:0'],
            ];
        }

        if ($this->routeIs('customer-subscriptions.pause') || $this->routeIs('customer-subscriptions.resume')) {
            return [
                'lock_version' => ['required', 'integer', 'min:0'],
            ];
        }

        return [
            'customer_id' => ['required', 'uuid'],
            'subscription_plan_id' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'O cliente é obrigatório.',
            'subscription_plan_id.required' => 'O plano de assinatura é obrigatório.',
        ];
    }
}
