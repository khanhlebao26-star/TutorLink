<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $minAge = $this->input('role') === 'tutor' ? 18 : 15;
        $maxDate = now()->subYears($minAge)->toDateString();

        return [
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'role' => ['required', 'string', 'in:customer,tutor'],
            'date_of_birth' => [
                'required',
                'date',
                'before_or_equal:'.$maxDate,
            ],
            'terms_version' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * Tùy chỉnh thông báo lỗi rõ ràng cho FE
     */
    public function messages(): array
    {
        $minAge = $this->input('role') === 'tutor' ? 18 : 15;

        return [
            'date_of_birth.before_or_equal' => "You must be at least {$minAge} years old.",
        ];
    }
}
