<?php

namespace App\Http\Resources;

use App\Support\TutorProfileRevisions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'specialization_id' => $this->specializations->first()?->id,
            'avatar_file_id' => $this->avatar_file_id,
            'verification_document_file_id' => $this->verificationDocuments->first()?->file_id,
            'verification_documents' => $this->when($request->user()?->id === $this->user_id, fn () => $this->verificationDocuments->map(fn ($document) => [
                'id' => (int) $document->id, 'file_id' => (int) $document->file_id,
                'tutor_profile_id' => (int) $document->tutor_profile_id,
                'document_type' => $document->document_type, 'status' => $document->status,
                'expires_at' => $document->expires_at?->toISOString(),
            ])->values()),
            'qualification_claims' => $this->qualification_claims ?? [],
            'revision' => app(TutorProfileRevisions::class)->latest($this->resource),
            'headline' => $this->headline,
            'bio' => $this->bio,
            'experience_years' => $this->experience_years,
            'status' => $this->status,
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
