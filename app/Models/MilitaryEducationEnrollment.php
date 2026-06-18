<?php

namespace App\Models;

use App\Enums\MilitaryEducationEnrollmentStatus;
use App\Enums\Semester;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilitaryEducationEnrollment extends Model
{
    protected $fillable = [
        'student_id',
        'course_id',
        'year_id',
        'semester',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => MilitaryEducationEnrollmentStatus::class,
            'semester' => Semester::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(MilitaryEducationCourse::class, 'course_id');
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }
}
