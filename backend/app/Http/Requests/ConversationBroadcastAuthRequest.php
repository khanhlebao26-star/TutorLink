<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConversationBroadcastAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'socket_id' => [
                'required',
                'string',
                'max:100',
                'regex:/\A[0-9]+\.[0-9]+\z/',
            ],
            'channel_name' => [
                'required',
                'string',
                'max:100',
                'regex:/\Aprivate-conversations\.[1-9][0-9]*\z/',
            ],
        ];
    }
}
