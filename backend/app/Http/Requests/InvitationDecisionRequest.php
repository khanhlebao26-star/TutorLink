<?php

namespace App\Http\Requests;

class InvitationDecisionRequest extends ProgramApiRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['sometimes', 'nullable', 'string', 'max:2000']];
    }

    /** @return list<string> */
    protected function allowedFields(): array
    {
        return ['reason'];
    }
}
