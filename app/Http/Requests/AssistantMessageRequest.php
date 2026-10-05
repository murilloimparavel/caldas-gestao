<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssistantMessageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['content' => trim((string) $this->input('content'))]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:'.(int) config('assistant.max_prompt_length', 4000)],
        ];
    }

    public function content(): string
    {
        return trim((string) $this->validated('content'));
    }
}
