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
            'message_type' => ['required', 'string', 'in:text,file'],
            'body' => ['required_if:message_type,text', 'nullable', 'string', 'max:4000', 'regex:/[^\s\p{Z}]/u'],
            'file_ids' => ['required_if:message_type,file', 'array', 'min:1', 'max:5'],
            'file_ids.*' => ['required', 'integer', 'distinct', 'min:1', 'exists:files,id'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $input = $this->all();

            foreach (
                array_diff(
                    array_keys($input),
                    ['client_message_id', 'message_type', 'body', 'file_ids'],
                ) as $field
            ) {
                $validator->errors()->add(
                    $field,
                    'This field is not allowed.',
                );
            }

            $rawPayload = $this->isJson()
                ? json_decode($this->getContent())
                : null;

            $fileIds = is_object($rawPayload)
                && property_exists($rawPayload, 'file_ids')
                ? $rawPayload->file_ids
                : $this->input('file_ids');

            if (array_key_exists('file_ids', $input) && ! is_array($fileIds)) {
                $validator->errors()->add(
                    'file_ids',
                    'The file_ids field must be an array.',
                );
            }

            if (($input['message_type'] ?? null) === 'text' && is_array($fileIds) && $fileIds !== []) {
                $validator->errors()->add('file_ids', 'File attachments require a file message.');
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
