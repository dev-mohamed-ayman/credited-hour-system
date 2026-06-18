<?php

namespace App\Models;

use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasDeletionGuards;

    protected $fillable = ['name', 'country_id'];
    protected $blockingRelations = ['students'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
