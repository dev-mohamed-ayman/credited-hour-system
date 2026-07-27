<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrossLevelVisibility extends Model
{
    protected $table = 'cross_level_visibility';

    protected $fillable = [
        'source_level_id',
        'visible_level_id',
    ];

    public function sourceLevel(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'source_level_id');
    }

    public function visibleLevel(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'visible_level_id');
    }
}
