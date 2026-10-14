<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tutor'
            && $this->user()->status === 'active';
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'specialization_id' => [
                'sometimes',
                'integer',
                Rule::exists('specializations', 'id')
                    ->where(fn ($query) => $query->where('status', 'active')),
            ],
            'title' => ['sometimes', 'string', 'min:3', 'max:180'],
            'description' => ['sometimes', 'string', 'min:1'],
            'delivery_mode' => ['sometimes', Rule::in(['online', 'offline', 'hybrid'])],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'district' => ['sometimes', 'nullable', 'string', 'max:120'],
            'price_from_minor' => ['sometimes', 'integer', 'min:0'],
            'currency' => [
                'sometimes',
                Rule::in(['VND']),
            ],
            'price_unit' => ['sometimes', Rule::in(['session', 'hour', 'course'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
