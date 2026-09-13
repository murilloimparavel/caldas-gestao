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
        'custom_data',
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
            'custom_data' => ['nullable', 'array', 'max:12'],
            'custom_data.content_name' => ['nullable', 'string', 'max:100'],
            'custom_data.content_category' => ['nullable', 'string', 'max:100'],
            'custom_data.content_type' => ['nullable', 'string', 'max:50'],
            'custom_data.content_ids' => ['nullable', 'array', 'max:5'],
            'custom_data.content_ids.*' => ['string', 'max:100'],
            'custom_data.num_items' => ['nullable', 'integer', 'min:1', 'max:100'],
            'custom_data.utm_source' => ['nullable', 'string', 'max:100'],
            'custom_data.utm_medium' => ['nullable', 'string', 'max:100'],
            'custom_data.utm_campaign' => ['nullable', 'string', 'max:200'],
            'custom_data.utm_content' => ['nullable', 'string', 'max:200'],
            'custom_data.utm_term' => ['nullable', 'string', 'max:200'],
            'custom_data.fbclid' => ['nullable', 'string', 'max:255'],
            'custom_data.gclid' => ['nullable', 'string', 'max:255'],
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

            if (($source['host'] ?? null) !== $this->getHost() || ($source['path'] ?? '/') !== '/' || ! in_array($query['lp'] ?? null, [null, 'barber'], true)) {
                $validator->errors()->add('event_source_url', 'O evento deve vir de uma landing pública válida.');
            }
        }];
    }
}
