<?php

namespace App\Http\Requests;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class InventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', InventoryMovement::class) || Gate::allows('manage', Product::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $type = (string) $this->input('type');
        $quantityRule = $type === 'manual_count'
            ? ['required', 'integer', 'min:0']
            : ['required', 'integer', 'min:1'];

        return [
            'product_id' => ['required', 'uuid'],
            'type' => ['required', 'string', 'in:purchase_inflow,adjustment_loss,adjustment_gain,manual_count,sale_outflow'],
            'quantity' => $quantityRule,
            'unit_cost_cents' => ['nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:64'],
            'reference_id' => ['nullable', 'string', 'max:64'],
            'lock_version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
