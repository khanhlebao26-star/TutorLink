<?php

namespace App\Http\Requests;

class CancelSessionRequest extends CompleteSessionRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'reason' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
