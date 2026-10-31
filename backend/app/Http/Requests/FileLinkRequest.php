<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FileLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            /** Message links are disabled. Runtime currently supports verification_document; other listed resources remain unavailable. */
            'resource_type' => [
                'required',
                'string',
                'in:task,task_submission,announcement,verification_document',
            ],
            'resource_id' => ['required', 'integer', 'min:1'],
            'purpose' => ['nullable', 'string', 'max:50'],
        ];
    }
}
