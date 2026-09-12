<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreMetaConversionRequest extends FormRequest
{
    private const ALLOWED_FIELDS = [
        'event_name',
        'event_id',
        'event_source_url',
        'fbp',
        'fbc',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'event_name' => ['required', 'string', Rule::in(['PageView', 'Lead'])],
            'event_id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'event_source_url' => ['required', 'url:http,https', 'max:2048'],
            'fbp' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'fbc' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $unknownFields = array_diff(array_keys($this->all()), self::ALLOWED_FIELDS);

            if ($unknownFields !== []) {
                $validator->errors()->add('payload', 'Campos adicionais não são aceitos.');
            }

            $sourceUrl = (string) $this->input('event_source_url');
            $source = parse_url($sourceUrl);
            $query = [];

            if (is_array($source) && isset($source['query'])) {
                parse_str($source['query'], $query);
            }

            if (($source['host'] ?? null) !== $this->getHost() || ($source['path'] ?? '/') !== '/' || ($query['lp'] ?? null) !== 'barber') {
                $validator->errors()->add('event_source_url', 'O evento deve vir da landing de barbearias.');
            }
        }];
    }
}
