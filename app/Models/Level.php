<?php

namespace App\Models;

use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Level extends Model
{
    use HasDeletionGuards;

    protected $fillable = ['name', 'military_required_for_males', 'military_required_for_females'];
    protected $blockingRelations = ['sections', 'students'];

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'level_section');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
