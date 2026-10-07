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
            'resource_type' => [
                'required',
                'string',
                'in:message,task,task_submission,announcement,verification_document',
            ],
            'resource_id' => ['required', 'integer', 'min:1'],
            'purpose' => ['nullable', 'string', 'max:50'],
        ];
    }
}
