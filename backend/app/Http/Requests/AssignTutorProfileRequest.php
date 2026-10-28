<?php

namespace App\Http\Requests;

use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class AssignTutorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active'
            && $this->user()->hasPermission(AdminPermissions::ASSIGN_TUTOR_PROFILES);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'assigned_admin_id' => ['present', 'nullable', 'integer', 'exists:users,id'],
            'assignment_version' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:2', 'max:2000'],
        ];
    }
}
