<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttachTutorAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tutor';
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'file_id' => ['required', 'integer', 'exists:files,id'],
        ];
    }
}
