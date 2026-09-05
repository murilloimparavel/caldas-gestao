<?php

namespace App\Http\Requests\Settings;

use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class OnlineBookingGalleryReorderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $context = $this->attributes->get(TenantContext::class);

        return $context instanceof TenantContext && $context->unit instanceof Unit && Gate::allows('update', $context->unit);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['image_ids' => ['required', 'array', 'max:100'], 'image_ids.*' => ['required', 'uuid', 'distinct']];
    }
}
