<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class TelemetryBrowserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(['javascript_error', 'unhandled_rejection', 'inertia_http_exception', 'network_error'])],
            'code' => ['required', 'string', 'regex:/^(?:[1-5][0-9]{2}|Error|TypeError|ReferenceError|RangeError|SyntaxError|URIError|EvalError|AggregateError|NetworkError|AbortError|UnhandledRejection|network_error|invalid_response)$/'],
            'path' => ['sometimes', 'string', 'max:512', 'regex:#^/[^?\\x00-\\x20]*$#'],
            'frames' => ['sometimes', 'array', 'max:8'],
            'frames.*' => ['array:file,line,column'],
            'frames.*.file' => ['required', 'string', 'max:512'],
            'frames.*.line' => ['required', 'integer', 'min:1', 'max:10000000'],
            'frames.*.column' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['category', 'code', 'path', 'frames']) !== []) {
                $validator->errors()->add('payload', 'Unsupported telemetry fields.');
            }
        });
    }
}
