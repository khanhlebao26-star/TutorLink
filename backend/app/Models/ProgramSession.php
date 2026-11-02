<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProgramSession extends Model
{
    protected $fillable = [
        'title',
        'planned_starts_at',
        'planned_ends_at',
        'delivery_mode',
        'location_text',
        'meeting_url',
    ];

    protected $hidden = [
        'terminal_replay',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'program_id' => 'integer',
            'program_version' => 'integer',
            'sequence_number' => 'integer',
            'version' => 'integer',
            'planned_starts_at' => 'immutable_datetime',
            'planned_ends_at' => 'immutable_datetime',
            'terminal_replay' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function actual(): HasOne
    {
        return $this->hasOne(
            LessonSession::class,
            'program_session_id',
        );
    }
}
