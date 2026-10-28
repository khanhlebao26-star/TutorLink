<?php

namespace App\Http\Requests;

class ProgramActionRequest extends ProgramApiRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }

    /** @return list<string> */
    protected function allowedFields(): array
    {
        return [];
    }
}
