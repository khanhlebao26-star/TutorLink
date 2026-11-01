<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonSession extends Model
{
    protected $guarded = ['*'];

    protected $hidden = [
        'tutor_joined_at',
        'customer_joined_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'program_session_id' => 'integer',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    public function plannedSession(): BelongsTo
    {
        return $this->belongsTo(
            ProgramSession::class,
            'program_session_id',
        );
    }
}
