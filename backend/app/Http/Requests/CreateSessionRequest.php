<?php

namespace App\Http\Requests;

class CreateSessionRequest extends SessionApiRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return $this->scheduleRules(creating: true);
    }
}
