<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    protected $table = 'announcements';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'version' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'file_links', 'resource_id', 'file_id')
            ->wherePivot('resource_type', 'announcement')
            ->withPivot('purpose')
            ->orderBy('files.id');
    }
}
