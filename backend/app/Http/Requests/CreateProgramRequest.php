<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class CreateProgramRequest extends ProgramApiRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'customer')
                    ->where('status', 'active')
                    ->whereNull('deleted_at')),
            ],
            'source_listing_id' => ['sometimes', 'nullable', 'integer', 'exists:service_listings,id'],
            'title' => ['required', 'string', 'max:180'],
            'goal' => ['sometimes', 'nullable', 'string'],
            'level' => ['sometimes', 'nullable', 'string', 'max:80'],
            'delivery_mode' => ['required', Rule::in(['online', 'offline', 'hybrid'])],
            'agreed_fee_minor' => ['required', 'integer', 'min:0', 'max:9223372036854775807'],
            'currency' => ['sometimes', Rule::in(['VND'])],
            'fee_unit' => ['required', Rule::in(['session', 'hour', 'course'])],
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
            'customer_id',
            'source_listing_id',
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
}
