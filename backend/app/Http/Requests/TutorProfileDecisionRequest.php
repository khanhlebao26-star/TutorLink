<?php

namespace App\Http\Requests;

class TutorProfileDecisionRequest extends AdminDecisionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'assignment_version' => ['required', 'integer', 'min:0'],
            'expected_version' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
