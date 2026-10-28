<?php

namespace App\Support;

use App\Models\File;
use App\Models\Specialization;
use App\Models\TutorProfile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TutorProfileChecklist
{
    public function validFile(?File $file, int $ownerId, string $purpose): bool
    {
        return $file !== null && $file->owner_id === $ownerId && $file->purpose === $purpose
            && ($purpose !== 'verification_document' || $file->visibility === 'private')
            && $file->isComplete() && Storage::disk($file->disk)->exists($file->object_key);
    }

    /** @param array<string, mixed>|null $payload */
    public function validate(TutorProfile $profile, bool $approval = false, ?array $payload = null): void
    {
        $payload ??= $profile->only(['headline', 'bio', 'avatar_file_id', 'qualification_claims']);
        $specializationId = $payload['specialization_id'] ?? $profile->specializations()->where('status', 'active')->value('specializations.id');
        $documents = $profile->verificationDocuments()->with('file')->get()->filter(function ($document) use ($profile, $approval): bool {
            return $this->validFile($document->file, $profile->user_id, 'verification_document')
                && (! $approval || $document->status === 'approved')
                && ! in_array($document->status, ['rejected', 'expired'], true)
                && ($document->expires_at === null || $document->expires_at->isFuture());
        });
        $claims = $payload['qualification_claims'] ?? [];
        $requiredTypes = collect($claims)->pluck('type')->unique();
        $qualificationsValid = $requiredTypes->every(fn (string $type): bool => $documents->contains('document_type', $type));
        Validator::make([
            'headline' => trim($payload['headline'] ?? ''), 'bio' => trim($payload['bio'] ?? ''),
            'specialization_id' => Specialization::where('id', $specializationId)->where('status', 'active')->exists() ? $specializationId : null,
            'avatar_file_id' => $this->validFile(File::find($payload['avatar_file_id'] ?? null), $profile->user_id, 'avatar') ? $payload['avatar_file_id'] : null,
            'verification_document_file_id' => $documents->whereIn('document_type', ['identity_card', 'passport'])->isNotEmpty() ? 1 : null,
            'qualification_claims' => $qualificationsValid ? true : null,
        ], [
            'headline' => ['required'], 'bio' => ['required'], 'specialization_id' => ['required'],
            'avatar_file_id' => ['required'], 'verification_document_file_id' => ['required'], 'qualification_claims' => ['required'],
        ])->validate();
    }
}
