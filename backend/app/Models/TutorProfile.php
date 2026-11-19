<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TutorProfile extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'assignment_version' => 'integer',
            'qualification_claims' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (TutorProfile $profile): void {
            $name = $profile->user()
                ->value('full_name') ?? 'tutor';

            $base = Str::slug($name) ?: 'tutor';
            $suffix = '-'.$profile->id;

            $profile->forceFill([
                'public_slug' => Str::substr(
                    $base,
                    0,
                    180 - strlen($suffix),
                ).$suffix,
            ])->saveQuietly();
        });
    }

    public function scopeVisiblePublic(Builder $query): Builder
    {
        $document = "SELECT 1 FROM verification_documents d JOIN files f ON f.id = d.file_id WHERE d.tutor_profile_id = tutor_profiles.id AND d.status = 'approved' AND (d.expires_at IS NULL OR d.expires_at > ?) AND f.owner_id = tutor_profiles.user_id AND f.purpose = 'verification_document' AND f.scan_status = 'clean' AND f.visibility = 'private'";

        $query->where('tutor_profiles.status', 'approved')
            ->whereNull('tutor_profiles.suspended_at')
            ->whereHas('user', fn (Builder $user) => $user->where('role', 'tutor')->where('status', 'active')->whereNull('suspended_at')->whereNotNull('email_verified_at'));

        if (! config('tutor.verification_documents_required', true)) {
            return $query;
        }

        return $query
            ->whereRaw("EXISTS ({$document} AND d.document_type IN ('identity_card', 'passport'))", [now('UTC')])
            ->whereRaw("NOT EXISTS (SELECT 1 FROM jsonb_array_elements(COALESCE(tutor_profiles.qualification_claims, '[]'::jsonb)) claim WHERE COALESCE(claim->>'type', '') NOT IN ('degree', 'certificate') OR NOT EXISTS ({$document} AND d.document_type = claim->>'type'))", [now('UTC')]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specializations(): BelongsToMany
    {
        return $this->belongsToMany(Specialization::class, 'tutor_specializations');
    }

    public function avatarFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'avatar_file_id');
    }

    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(VerificationDocument::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(ServiceListing::class);
    }

    public function availabilityRules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class);
    }
}
