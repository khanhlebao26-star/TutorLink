<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Message extends Model
{
    protected $guarded = ['id'];

    protected $hidden = [
        'payload_hash',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(
            File::class,
            'file_links',
            'resource_id',
            'file_id',
        )
            ->withPivotValue('resource_type', 'message')
            ->withPivot([
                'resource_type',
                'purpose',
                'created_at',
            ]);
    }
}
