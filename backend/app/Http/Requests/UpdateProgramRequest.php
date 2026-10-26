<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProgramRequest extends ProgramApiRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'title' => ['sometimes', 'string', 'max:180'],
            'goal' => ['sometimes', 'nullable', 'string'],
            'level' => ['sometimes', 'nullable', 'string', 'max:80'],
            'delivery_mode' => ['sometimes', Rule::in(['online', 'offline', 'hybrid'])],
            'agreed_fee_minor' => ['sometimes', 'integer', 'min:0', 'max:9223372036854775807'],
            'currency' => ['sometimes', Rule::in(['VND'])],
            'fee_unit' => ['sometimes', Rule::in(['session', 'hour', 'course'])],
            'location_text' => ['sometimes', 'nullable', 'string'],
            'meeting_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'schedule_note' => ['sometimes', 'nullable', 'string'],
            'timezone' => ['sometimes', 'string', 'timezone', 'max:64'],
            'planned_session_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
        ];
    }

    /** @return list<string> */
    protected function allowedFields(): array
    {
        return [
            'expected_version',
            'title',
            'goal',
            'level',
            'delivery_mode',
            'agreed_fee_minor',
            'currency',
            'fee_unit',
            'location_text',
            'meeting_url',
            'schedule_note',
            'timezone',
            'planned_session_count',
        ];
    }

    /** @param array<string, mixed> $input */
    protected function validatePayload(Validator $validator, array $input): void
    {
        if (array_intersect(array_keys($input), array_diff($this->allowedFields(), ['expected_version'])) === []) {
            $validator->errors()->add('expected_version', 'At least one offer field must be provided.');
        }
    }
}
