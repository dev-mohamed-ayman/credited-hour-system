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

    public function canSeeLevels(): BelongsToMany
    {
        return $this->belongsToMany(Level::class, 'cross_level_visibility', 'source_level_id', 'visible_level_id');
    }

    public function visibleToLevels(): BelongsToMany
    {
        return $this->belongsToMany(Level::class, 'cross_level_visibility', 'visible_level_id', 'source_level_id');
    }

    /**
     * @return array<int, int>
     */
    public static function getVisibleLevelIds(int $sourceLevelId): array
    {
        return CrossLevelVisibility::query()
            ->where('source_level_id', $sourceLevelId)
            ->pluck('visible_level_id')
            ->all();
    }
}
