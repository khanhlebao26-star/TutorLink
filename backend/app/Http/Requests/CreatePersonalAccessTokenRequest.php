<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePersonalAccessTokenRequest extends FormRequest
{
    private const ABILITIES = [
        'account:read',
        'my-listings:read',
        'my-listings:write',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(self::ABILITIES),
            ],
            'expires_in_days' => ['sometimes', 'integer', 'between:1,90'],
        ];
    }
}
