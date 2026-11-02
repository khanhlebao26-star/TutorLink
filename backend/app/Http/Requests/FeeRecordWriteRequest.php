<?php

namespace App\Http\Requests;

use App\Models\FeeRecord;
use App\Support\ApiErrorResponse;
use App\Support\BeD04Workspace;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

abstract class FeeRecordWriteRequest extends BeD04ApiRequest
{
    public function authorize(): bool
    {
        $requestId = (string) $this->attributes->get('request_id');
        $programId = $this->route('program');

        if ($programId === null) {
            $fee = FeeRecord::query()->find((int) $this->route('feeRecord'));

            if ($fee === null) {
                throw new HttpResponseException(ApiErrorResponse::make(404, 'fee_record_not_found', 'Fee record not found.', $requestId));
            }

            $programId = (int) $fee->program_id;
            $this->attributes->set('d04_fee_record_id', (int) $fee->id);
        }

        $workspace = app(BeD04Workspace::class);
        $workspace->visible($this->user(), (int) $programId, $requestId);
        $program = $workspace->owner($this->user(), (int) $programId, $requestId);
        $this->attributes->set('d04_program_id', (int) $program->id);

        return true;
    }

    protected function validatePayload(Validator $validator, array $input): void
    {
        $key = $this->header('Idempotency-Key');

        if (! is_string($key) || ! Str::isUuid($key)) {
            $validator->errors()->add('Idempotency-Key', 'A UUID Idempotency-Key header is required.');

            return;
        }

        $this->attributes->set('d04_idempotency_key', strtolower($key));
    }

    /** @return list<string> */
    protected function reasonRules(bool $required): array
    {
        return array_values(array_filter([
            'bail',
            $required ? 'required' : 'sometimes',
            $required ? null : 'nullable',
            'string',
            'max:2000',
            'regex:/[^\s\p{Z}]/u',
        ]));
    }
}
