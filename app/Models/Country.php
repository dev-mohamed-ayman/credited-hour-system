<?php

namespace App\Models;

use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasDeletionGuards;

    protected $fillable = ['name'];
    protected $blockingRelations = ['cities', 'students'];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
