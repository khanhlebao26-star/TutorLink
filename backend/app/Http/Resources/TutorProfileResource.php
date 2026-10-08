<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = match ($this->approval_status) {
            'active' => 'approved',
            'changes_requested' => 'changes_requested',
            'rejected' => 'rejected',
            default => $this->submitted_at ? 'submitted' : 'draft',
        };

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'specialization_id' => $this->specializations->first()?->id,
            'avatar_file_id' => $this->avatar_file_id,
            'verification_document_file_id' => $this->verificationDocuments->first()?->file_id,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'experience_years' => $this->experience_years,
            'status' => $status,
            'approval_status' => $this->approval_status,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'review_reason' => $this->review_reason,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'suspended_at' => $this->suspended_at?->toISOString(),
            'suspension_reason' => $this->suspension_reason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
