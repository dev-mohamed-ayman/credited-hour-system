<?php

namespace App\Livewire\Admin\MilitaryEducationCourses;

use App\Enums\MilitaryEducationCourseStatus;
use App\Enums\MilitaryEducationEnrollmentStatus;
use App\Models\MilitaryEducationCourse;
use App\Models\MilitaryEducationEnrollment;
use Livewire\Component;

class Show extends Component
{
    public MilitaryEducationCourse $course;

    public function mount($militaryEducationCourse)
    {
        $this->course = MilitaryEducationCourse::with('enrollments.student', 'enrollments.year')
            ->findOrFail($militaryEducationCourse);
    }

    public function updateEnrollmentStatus($enrollmentId, $status)
    {
        $enrollment = MilitaryEducationEnrollment::findOrFail($enrollmentId);
        $statusEnum = $status === 'passed' ? MilitaryEducationEnrollmentStatus::PASSED : MilitaryEducationEnrollmentStatus::FAILED;
        $enrollment->update(['status' => $statusEnum]);

        if ($statusEnum === MilitaryEducationEnrollmentStatus::PASSED) {
            // Also update student's military_education_passed flag
            $enrollment->student()->update(['military_education_passed' => true]);
        }

        $this->dispatch('toast', ['message' => 'تم تحديث حالة الطالب بنجاح', 'type' => 'success']);
    }

    public function closeCourse()
    {
        $this->course->update(['status' => MilitaryEducationCourseStatus::CLOSED]);
        $this->dispatch('toast', ['message' => 'تم إغلاق الدورة بنجاح', 'type' => 'success']);
        $this->course->refresh();
    }

    public function render()
    {
        return view('livewire.admin.military-education-courses.show')
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
