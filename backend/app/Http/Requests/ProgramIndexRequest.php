<?php

namespace App\Http\Requests;

class ProgramIndexRequest extends ProgramApiRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /** @return list<string> */
    protected function allowedFields(): array
    {
        return ['page', 'per_page'];
    }
}
