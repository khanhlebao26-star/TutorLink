<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Program extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'agreed_fee_minor' => 'integer',
            'planned_session_count' => 'integer',
            'activated_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function ownerTutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class, 'owner_tutor_profile_id');
    }

    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        return match ($actor->role) {
            'tutor' => $query->whereHas(
                'ownerTutorProfile',
                fn (Builder $profile) => $profile->where('user_id', $actor->id),
            ),
            'customer' => $query->whereExists(fn ($membership) => $membership
                ->selectRaw('1')
                ->from('program_members')
                ->whereColumn('program_members.program_id', 'programs.id')
                ->where('program_members.user_id', $actor->id)
                ->where('program_members.member_role', 'customer')
                ->where('program_members.status', 'active')),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function scopeWithActionContext(Builder $query): Builder
    {
        return $query
            ->addSelect([
                'has_unexpired_pending_invitation' => DB::table('program_invitations')
                    ->selectRaw('1')
                    ->whereColumn('program_invitations.program_id', 'programs.id')
                    ->where('program_invitations.status', 'pending')
                    ->where('program_invitations.expires_at', '>', now('UTC'))
                    ->limit(1),
            ]);
    }
}
