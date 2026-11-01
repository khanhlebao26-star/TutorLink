<?php

namespace App\Http\Requests;

class CompleteSessionRequest extends SessionApiRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'expected_version' => $this->versionRules(),
        ];
    }
}
