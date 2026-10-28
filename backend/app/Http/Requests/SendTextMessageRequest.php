<?php

namespace App\Http\Requests;

use App\Support\ApiErrorResponse;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class SendTextMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => str_replace(["\r\n", "\r"], "\n", $this->input('body'))]);
        }

        if (is_string($this->input('client_message_id'))) {
            $this->merge(['client_message_id' => strtolower($this->input('client_message_id'))]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'client_message_id' => ['required', 'uuid'],
            'message_type' => ['required', 'string', 'in:text'],
            'body' => ['required', 'string', 'max:4000', 'regex:/[^\s\p{Z}]/u'],
            'file_ids' => ['sometimes', 'array', 'size:0'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['client_message_id', 'message_type', 'body', 'file_ids']) as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }
        });
    }

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
}
