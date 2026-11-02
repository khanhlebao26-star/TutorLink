<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressRecord extends Model
{
    protected $table = 'progress_records';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'version' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
