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
