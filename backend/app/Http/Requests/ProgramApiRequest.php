<?php

namespace App\Http\Requests;

use App\Support\ApiErrorResponse;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

abstract class ProgramApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    abstract public function rules(): array;

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(ApiErrorResponse::make(
            422,
            'validation_failed',
            'The given data was invalid.',
            (string) $this->attributes->get('request_id'),
            $validator->errors()->messages(),
        ));
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $input = $this->isMethod('GET')
                ? $this->query->all()
                : ($this->isJson() ? $this->json()->all() : $this->request->all());

            foreach (array_diff(array_keys($input), $this->allowedFields()) as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            $this->validatePayload($validator, $input);
        });
    }

    /** @return list<string> */
    abstract protected function allowedFields(): array;

    /** @param array<string, mixed> $input */
    protected function validatePayload(Validator $validator, array $input): void {}
}
