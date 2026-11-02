<?php

namespace App\Http\Requests;

class UpdateFeeRecordRequest extends FeeRecordWriteRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'expected_version' => $this->expectedVersionRules(),
            'amount_minor' => $this->strictIntegerRules(0, 9007199254740991, required: true),
            'reason' => $this->reasonRules(required: true),
        ];
    }
}
