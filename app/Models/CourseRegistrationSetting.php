<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseRegistrationSetting extends Model
{
    protected $fillable = [
        'level_id',
        'term_type',
        'max_optional_courses',
    ];

    protected function casts(): array
    {
        return [
            'term_type' => Semester::class,
            'max_optional_courses' => 'integer',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}
