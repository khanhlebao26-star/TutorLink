<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class Review extends Model
{
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::saving(fn () => throw new LogicException('Use shared business actions.'));
        static::deleting(fn () => throw new LogicException('Use shared business actions.'));
    }
}
