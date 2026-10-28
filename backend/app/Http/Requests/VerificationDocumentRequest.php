<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerificationDocumentRequest extends FormRequest
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
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'document_type' => ['required', 'string', 'in:identity_card,passport,degree,certificate,other'],
        ];
    }
}
