<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Level extends Model
{
    protected $fillable = ['name'];

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'level_section');
    }
}
