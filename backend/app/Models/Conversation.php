<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['direct_key'];

    protected $attributes = [
        'type' => 'direct',
        'status' => 'active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'activated_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'conversation_members',
            'conversation_id',
            'user_id',
        )->withPivot([
            'last_read_message_id',
            'last_read_at',
            'joined_at',
        ]);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function contextListing(): BelongsTo
    {
        return $this->belongsTo(
            ServiceListing::class,
            'context_listing_id',
        );
    }
}
