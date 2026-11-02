<?php

namespace App\Http\Requests;

use App\Support\BeD04Workspace;

class StoreProgressRecordRequest extends BeD04ApiRequest
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
            'metric_name' => ['required', 'string', 'in:progress_percent'],
            'value' => $this->strictIntegerRules(0, 100, required: true),
            'note' => ['sometimes', 'nullable', 'string', 'max:2000', 'regex:/[^\s\p{Z}]/u'],
        ];
    }
}
