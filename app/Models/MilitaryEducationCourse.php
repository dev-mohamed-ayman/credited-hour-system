<?php

namespace App\Models;

use App\Enums\MilitaryEducationCourseStatus;
use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MilitaryEducationCourse extends Model
{
    use HasDeletionGuards;

    protected $fillable = [
        'name',
        'gender',
        'capacity',
        'fee_amount',
        'status',
    ];

    protected $blockingRelations = ['enrollments'];

    protected function casts(): array
    {
        return [
            'status' => MilitaryEducationCourseStatus::class,
            'fee_amount' => 'decimal:2',
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(MilitaryEducationEnrollment::class, 'course_id');
    }

    public function registeredStudentsCount(): int
    {
        return $this->enrollments()->count();
    }
}
