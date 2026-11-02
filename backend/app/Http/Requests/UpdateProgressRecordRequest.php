<?php

namespace App\Http\Requests;

use App\Models\ProgressRecord;
use App\Support\ApiErrorResponse;
use App\Support\BeD04Workspace;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class UpdateProgressRecordRequest extends BeD04ApiRequest
{
    public function authorize(): bool
    {
        $record = ProgressRecord::query()->find((int) $this->route('progressRecord'));

        if ($record === null) {
            throw new HttpResponseException(ApiErrorResponse::make(
                404,
                'progress_record_not_found',
                'Progress record not found.',
                (string) $this->attributes->get('request_id'),
            ));
        }

        $workspace = app(BeD04Workspace::class);
        $workspace->visible($this->user(), (int) $record->program_id, (string) $this->attributes->get('request_id'));
        $program = $workspace->owner($this->user(), (int) $record->program_id, (string) $this->attributes->get('request_id'));
        $this->attributes->set('d04_program_id', (int) $program->id);

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'expected_version' => $this->expectedVersionRules(),
            'value' => $this->strictIntegerRules(0, 100),
            'note' => ['sometimes', 'nullable', 'string', 'max:2000', 'regex:/[^\s\p{Z}]/u'],
        ];
    }

    protected function validatePayload(Validator $validator, array $input): void
    {
        if (! array_key_exists('value', $input) && ! array_key_exists('note', $input)) {
            $validator->errors()->add('expected_version', 'At least one mutable field is required.');
        }
    }
}
