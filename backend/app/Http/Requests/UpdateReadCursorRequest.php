<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\ApiErrorResponse;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class UpdateReadCursorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $requestId = (string) $this->attributes->get('request_id');

        if (! $actor instanceof User) {
            throw new HttpResponseException(ApiErrorResponse::make(401, 'unauthenticated', 'Authentication is required.', $requestId));
        }

        $visible = in_array($actor->role, ['customer', 'tutor'], true)
            && DB::table('conversations as c')
                ->join('conversation_members as member', 'member.conversation_id', '=', 'c.id')
                ->where('c.id', $this->route('conversation'))->where('member.user_id', $actor->id)
                ->where('c.type', 'direct')->whereIn('c.status', ['active', 'cancelled'])->exists();

        if (! $visible) {
            throw new HttpResponseException(ApiErrorResponse::make(404, 'conversation_not_found', 'Conversation not found.', $requestId));
        }

        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['last_read_message_id' => ['required', 'integer:strict', 'min:1']];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['last_read_message_id']) as $field) {
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
