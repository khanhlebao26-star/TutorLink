<?php

namespace App\Http\Requests;

use App\Models\Announcement;
use App\Support\ApiErrorResponse;
use App\Support\BeD04Workspace;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class UpdateAnnouncementRequest extends BeD04ApiRequest
{
    public function authorize(): bool
    {
        $requestId = (string) $this->attributes->get('request_id');
        $announcement = Announcement::query()->find((int) $this->route('announcement'));

        if ($announcement === null) {
            throw new HttpResponseException(ApiErrorResponse::make(
                404,
                'announcement_not_found',
                'Announcement not found.',
                $requestId,
            ));
        }

        $workspace = app(BeD04Workspace::class);
        $workspace->visible($this->user(), (int) $announcement->program_id, $requestId);
        $program = $workspace->owner($this->user(), (int) $announcement->program_id, $requestId);
        $this->attributes->set('d04_program_id', (int) $program->id);

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'expected_version' => $this->expectedVersionRules(),
            'title' => ['sometimes', 'required', 'string', 'max:180', 'regex:/[^\s\p{Z}]/u'],
            'body' => ['sometimes', 'required', 'string', 'max:10000', 'regex:/[^\s\p{Z}]/u'],
            'file_ids' => ['sometimes', 'array', 'max:5'],
            'file_ids.*' => $this->strictIntegerRules(1, PHP_INT_MAX, required: true),
        ];
    }

    protected function validatePayload(Validator $validator, array $input): void
    {
        if (! array_intersect(['title', 'body', 'file_ids'], array_keys($input))) {
            $validator->errors()->add('expected_version', 'At least one mutable field is required.');
        }

        if (array_key_exists('file_ids', $input) && is_array($input['file_ids']) && ! array_is_list($input['file_ids'])) {
            $validator->errors()->add('file_ids', 'The field must be a JSON array.');
        }

        $ids = $input['file_ids'] ?? [];
        if (is_array($ids) && count($ids) !== count(array_unique($ids, SORT_REGULAR))) {
            $validator->errors()->add('file_ids', 'File IDs must be unique.');
        }
    }
}
