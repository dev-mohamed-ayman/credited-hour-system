<?php

namespace App\Models;

use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasDeletionGuards;

    protected $fillable = [
        'code',
        'name',
        'hours',
        'is_selected',
        'is_active',
        'department_id',
        'level_id',
        'semester',
    ];

    protected $casts = [
        'is_selected' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $blockingRelations = ['sections', 'registrationCourses', 'dependents'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'course_section');
    }

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'course_id',
            'prerequisite_course_id'
        )->withTimestamps();
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'prerequisite_course_id',
            'course_id'
        )->withTimestamps();
    }

    public function registrationCourses(): HasMany
    {
        return $this->hasMany(RegistrationCourse::class);
    }
}
