<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class UpdateSessionRequest extends SessionApiRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'expected_version' => $this->versionRules(),
            ...$this->scheduleRules(creating: false),
        ];
    }

    /** @param array<string, mixed> $input */
    protected function validatePayload(
        Validator $validator,
        array $input,
    ): void {
        parent::validatePayload($validator, $input);

        $editableFields = array_keys(
            $this->scheduleRules(creating: false),
        );

        if (
            array_intersect(
                array_keys($input),
                $editableFields,
            ) === []
        ) {
            $validator->errors()->add(
                'session',
                'At least one editable Session field is required.',
            );
        }
    }
}
