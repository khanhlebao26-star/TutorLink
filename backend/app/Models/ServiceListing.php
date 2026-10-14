<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceListing extends Model
{
    protected $guarded = ['id'];

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
                    ->whereHas('user', fn(Builder $user) => $user->where('status', 'active'));
            })
            ->whereHas('specialization', function (Builder $specialization): void {
                $specialization
                    ->where('status', 'active')
                    ->whereHas('subcategory', function (Builder $subcategory): void {
                        $subcategory
                            ->where('status', 'active')
                            ->whereHas('category', fn(Builder $category) => $category->where('status', 'active'));
                    });
            });
    }
}