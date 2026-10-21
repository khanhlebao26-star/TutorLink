<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->role === 'tutor'
            && $user->status === 'active'
            && $user->hasVerifiedEmail()
            && $user->tutorProfile?->status === 'approved';
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'specialization_id' => [
                'required',
                'integer',
                Rule::exists('specializations', 'id')
                    ->where(fn ($query) => $query->where('status', 'active')),
            ],
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['required', 'string', 'min:1'],
            'delivery_mode' => ['required', Rule::in(['online', 'offline', 'hybrid'])],
            'city' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'price_from_minor' => ['required', 'integer', 'min:0'],
            'currency' => [
                'required',
                Rule::in(['VND']),
            ],
            'price_unit' => ['required', Rule::in(['session', 'hour', 'course'])],
        ];
    }
}
