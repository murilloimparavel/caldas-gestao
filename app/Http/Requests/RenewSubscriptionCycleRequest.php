<?php

namespace App\Http\Requests;

use App\Models\CustomerSubscription;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class RenewSubscriptionCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscription = $this->route('customer_subscription') ?? $this->route('customerSubscription');

        return $subscription instanceof CustomerSubscription
            ? Gate::allows('renew', $subscription)
            : Gate::allows('renew', CustomerSubscription::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'as_of' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
