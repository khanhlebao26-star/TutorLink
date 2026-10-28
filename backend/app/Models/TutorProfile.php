<?php

namespace App\Models;

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
