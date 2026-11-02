<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

abstract class BeD04ApiRequest extends ProgramApiRequest
{
    /** @return list<string> */
    protected function allowedFields(): array
    {
        return array_keys($this->rules());
    }

    /** @return list<string> */
    protected function expectedVersionRules(): array
    {
        return ['bail', 'required', 'integer:strict', 'min:1', 'max:2147483647'];
    }

    /** @return list<string> */
    protected function strictIntegerRules(int $minimum, int $maximum, bool $required = false): array
    {
        return array_values(array_filter([
            'bail',
            $required ? 'required' : 'sometimes',
            'integer:strict',
            'min:'.$minimum,
            'max:'.$maximum,
        ]));
    }

    protected function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        if (! $this->isMethod('GET')) {
            $validator->after(function (Validator $validator): void {
                try {
                    $body = json_decode($this->getContent(), false, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $body = null;
                }

                if (! $this->isJson() || ! $body instanceof \stdClass) {
                    $validator->errors()->add('body', 'A JSON object is required.');
                }

                foreach ($this->json()->all() as $field => $value) {
                    if (
                        is_string($field)
                        && is_string($value)
                        && preg_match('/[^\s\p{Z}]/u', $value) !== 1
                        && ! $validator->errors()->has($field)
                    ) {
                        $validator->errors()->add($field, 'The field must contain non-whitespace text.');
                    }
                }

                foreach (array_keys($this->query->all()) as $field) {
                    if (is_string($field) && ! $validator->errors()->has($field)) {
                        $validator->errors()->add($field, 'Query parameters are not supported for this request.');
                    }
                }
            });
        }
    }
}
