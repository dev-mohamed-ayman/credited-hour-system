<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The most hours a student of a department and level may register in a term
 * (chs: students_registration_hour, by specialization and level).
 */
class RegistrationHourLimit extends Model
{
    protected $fillable = [
        'department_id',
        'level_id',
        'semester',
        'max_hours',
    ];

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'max_hours' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}
