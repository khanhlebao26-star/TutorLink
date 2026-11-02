<?php

namespace App\Http\Requests;

use App\Support\BeD04Workspace;

class ProgressRecordIndexRequest extends BeD04ApiRequest
{
    public function authorize(): bool
    {
        $program = app(BeD04Workspace::class)->visible(
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
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
