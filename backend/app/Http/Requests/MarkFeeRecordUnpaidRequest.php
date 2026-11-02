<?php

namespace App\Http\Requests;

class MarkFeeRecordUnpaidRequest extends FeeRecordWriteRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'expected_version' => $this->expectedVersionRules(),
            'reason' => $this->reasonRules(required: false),
        ];
    }
}
