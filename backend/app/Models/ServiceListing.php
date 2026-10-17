<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceListing extends Model
{
    protected $guarded = ['id'];

    /** @return list<string> */
    public function allowedOwnerActions(User $user): array
    {
        $profile = $user->tutorProfile;

        if ($user->role !== 'tutor' || $user->status !== 'active'
            || ! $user->hasVerifiedEmail() || ! $profile
            || $profile->id !== $this->tutor_profile_id
            || $profile->approval_status !== 'active' || $profile->suspended_at
            || $this->status === 'cancelled') {
            return [];
        }

        $actions = ['edit'];

        if ($this->status === 'active') {
            $actions[] = 'pause';
        } elseif (in_array($this->status, ['draft', 'inactive'], true)
            && $this->specialization?->status === 'active'
            && $this->specialization?->subcategory?->status === 'active'
            && $this->specialization?->subcategory?->category?->status === 'active') {
            $actions[] = 'publish';
        }

        $actions[] = 'archive';

        return $actions;
    }

    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    public function scopeVisiblePublic(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->where('currency', 'VND')
            ->whereHas('tutorProfile', function (Builder $profile): void {
                $profile
                    ->where('approval_status', 'active')
                    ->whereNull('suspended_at')
                    ->whereHas('user', fn (Builder $user) => $user->where('status', 'active'));
            })
            ->whereHas('specialization', function (Builder $specialization): void {
                $specialization
                    ->where('status', 'active')
                    ->whereHas('subcategory', function (Builder $subcategory): void {
                        $subcategory
                            ->where('status', 'active')
                            ->whereHas('category', fn (Builder $category) => $category->where('status', 'active'));
                    });
            });
    }
}
