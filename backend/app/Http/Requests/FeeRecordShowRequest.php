<?php

namespace App\Http\Requests;

use App\Models\FeeRecord;
use App\Support\ApiErrorResponse;
use App\Support\BeD04Workspace;
use Illuminate\Http\Exceptions\HttpResponseException;

class FeeRecordShowRequest extends BeD04ApiRequest
{
    public function authorize(): bool
    {
        $requestId = (string) $this->attributes->get('request_id');
        $fee = FeeRecord::query()->find((int) $this->route('feeRecord'));

        if ($fee === null) {
            throw new HttpResponseException(ApiErrorResponse::make(404, 'fee_record_not_found', 'Fee record not found.', $requestId));
        }

        app(BeD04Workspace::class)->visible($this->user(), (int) $fee->program_id, $requestId);

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [];
    }

    protected function allowedFields(): array
    {
        return [];
    }
}
