<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AvailabilityRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tutor'
            && $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return [
            'rules' => ['required', 'array', 'max:50'],

            'rules.*.weekday' => [
                'required',
                'integer',
                'between:0,6',
            ],

            'rules.*.start_time' => [
                'required',
                'date_format:H:i',
            ],

            'rules.*.end_time' => [
                'required',
                'date_format:H:i',
            ],

            'rules.*.timezone' => [
                'required',
                'timezone',
            ],
        ];
    }
}
