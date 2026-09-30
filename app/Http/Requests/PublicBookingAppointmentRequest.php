<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class PublicBookingAppointmentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'phone' => is_string($this->input('phone')) ? trim($this->input('phone')) : $this->input('phone'),
            'email' => is_string($this->input('email')) ? trim($this->input('email')) : $this->input('email'),
            'notes' => is_string($this->input('notes')) ? trim($this->input('notes')) : $this->input('notes'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'uuid', 'required_without:service_ids'],
            'service_ids' => ['nullable', 'array', 'min:1', 'max:8', 'required_without:service_id'],
            'service_ids.*' => ['required', 'uuid', 'distinct'],
            'professional_id' => ['required', 'uuid'],
            'starts_at' => ['required', 'date'],
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $phone = $this->input('phone');

            if (! is_string($phone) || $phone === '') {
                return;
            }

            if (! preg_match('/^\+?[0-9\s().-]+$/u', $phone)) {
                $validator->errors()->add('phone', 'Informe um telefone brasileiro válido com DDD.');

                return;
            }

            $digits = preg_replace('/\D+/', '', $phone);

            if (! is_string($digits) || preg_match('/^(?:55)?[1-9][0-9](?:[2-5][0-9]{7}|9[0-9]{8})$/D', $digits) !== 1) {
                $validator->errors()->add('phone', 'Informe um telefone brasileiro válido com DDD.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe seu nome.',
            'name.max' => 'O nome deve ter no máximo 160 caracteres.',
            'phone.required' => 'Informe seu telefone com DDD.',
            'phone.max' => 'O telefone deve ter no máximo 40 caracteres.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail deve ter no máximo 255 caracteres.',
            'starts_at.required' => 'Selecione um horário para o atendimento.',
        ];
    }

    /**
     * @param  string|null  $key
     * @param  mixed  $default
     * @return array{service_id?: string|null, service_ids?: list<string>, professional_id: string, starts_at: string, name: string, phone: string, email?: string|null, notes?: string|null}
     */
    public function validated($key = null, $default = null): array
    {
        /** @var array{service_id?: string|null, service_ids?: list<string>, professional_id: string, starts_at: string, name: string, phone: string, email?: string|null, notes?: string|null} */
        return parent::validated($key, $default);
    }
}
