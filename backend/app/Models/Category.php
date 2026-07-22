<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ["name", "slug", "description"];

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }
}
