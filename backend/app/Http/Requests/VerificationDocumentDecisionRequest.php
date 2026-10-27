<?php

namespace App\Http\Requests;

use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class VerificationDocumentDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active' && $this->user()->hasPermission(AdminPermissions::DECIDE_VERIFICATION_DOCUMENTS);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:2', 'max:2000'], 'assignment_version' => ['required', 'integer', 'min:0']];
    }
}
