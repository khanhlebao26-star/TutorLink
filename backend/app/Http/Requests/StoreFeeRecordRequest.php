<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class StoreFeeRecordRequest extends FeeRecordWriteRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/D'],
            'amount_minor' => $this->strictIntegerRules(0, 9007199254740991, required: true),
            'currency' => ['sometimes', 'required', 'string', 'in:VND'],
        ];
    }

    protected function validatePayload(Validator $validator, array $input): void
    {
        parent::validatePayload($validator, $input);

        if (isset($input['period']) && ! FeeRecordIndexRequest::validPeriod($input['period'])) {
            $validator->errors()->add('period', 'The period must be a valid calendar month in YYYY-MM format.');
        }
    }
}
