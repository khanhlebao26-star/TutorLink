<?php

namespace App\Http\Requests;

use App\Support\BeD04Workspace;
use Illuminate\Validation\Validator;

class StoreAnnouncementRequest extends BeD04ApiRequest
{
    public function authorize(): bool
    {
        $program = app(BeD04Workspace::class)->owner(
            $this->user(),
            (int) $this->route('program'),
            (string) $this->attributes->get('request_id'),
        );
        $this->attributes->set('d04_program', $program);

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180', 'regex:/[^\s\p{Z}]/u'],
            'body' => ['required', 'string', 'max:10000', 'regex:/[^\s\p{Z}]/u'],
            'file_ids' => ['sometimes', 'array', 'max:5'],
            'file_ids.*' => $this->strictIntegerRules(1, PHP_INT_MAX, required: true),
        ];
    }

    protected function validatePayload(Validator $validator, array $input): void
    {
        if (array_key_exists('file_ids', $input) && is_array($input['file_ids']) && ! array_is_list($input['file_ids'])) {
            $validator->errors()->add('file_ids', 'The field must be a JSON array.');
        }

        $ids = $input['file_ids'] ?? [];
        if (is_array($ids) && count($ids) !== count(array_unique($ids, SORT_REGULAR))) {
            $validator->errors()->add('file_ids', 'File IDs must be unique.');
        }
    }
}
