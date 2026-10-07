<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $guarded = ['id'];

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class);
    }
}
