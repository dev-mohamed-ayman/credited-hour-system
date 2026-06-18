<?php

namespace App\Observers;

use App\Models\MilitaryEducationCourse;
use App\Services\MilitaryEducationService;

class MilitaryEducationCourseObserver
{
    public function __construct(
        protected MilitaryEducationService $service
    ) {}

    public function created(MilitaryEducationCourse $course): void
    {
        if ($course->status === 'active') {
            $this->service->autoEnrollStudents($course);
        }
    }

    public function updated(MilitaryEducationCourse $course): void
    {
        // If we just activated a closed course
        if ($course->wasChanged('status') && $course->status === 'active') {
            $this->service->autoEnrollStudents($course);
        }
    }
}
