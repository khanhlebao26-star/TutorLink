<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TutorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tutor';
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'expected_version' => ['sometimes', 'integer', 'min:0'],
            'qualification_claims' => ['sometimes', 'array', 'max:20'],
            'qualification_claims.*' => ['array:type,name'],
            'qualification_claims.*.type' => ['required', Rule::in(['degree', 'certificate'])],
            'qualification_claims.*.name' => ['required', 'string', 'max:160'],
            'headline' => ['required', 'string', 'max:160'],
            'bio' => ['nullable', 'string'],
            'experience_years' => ['sometimes', 'integer', 'min:0', 'max:80'],
            'specialization_id' => [
                'required',
                'integer',
                Rule::exists('specializations', 'id')->where(
                    fn ($query) => $query->where('status', 'active'),
                ),
            ],
        ];
    }
}
