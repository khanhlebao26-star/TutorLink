<?php

namespace App\Http\Requests;

class SendInvitationRequest extends ProgramApiRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['expected_version' => ['required', 'integer', 'min:1', 'max:4294967295']];
    }

    /** @return list<string> */
    protected function allowedFields(): array
    {
        return ['expected_version'];
    }
}
