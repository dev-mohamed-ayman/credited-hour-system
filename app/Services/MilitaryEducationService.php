<?php

namespace App\Services;

use App\Enums\MilitaryEducationEnrollmentStatus;
use App\Models\Level;
use App\Models\MilitaryEducationCourse;
use App\Models\MilitaryEducationEnrollment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentFeeTicket;
use App\Models\Year;
use Illuminate\Support\Facades\DB;

class MilitaryEducationService
{
    public function getDefaultFee(): float
    {
        $setting = Setting::first();

        return $setting?->military_education_default_fee ?? 0;
    }

    public function autoEnrollStudents(MilitaryEducationCourse $course): void
    {
        DB::transaction(function () use ($course) {
            $eligibleStudents = $this->getEligibleStudents($course->gender);
            $studentsToEnroll = $eligibleStudents->take($course->capacity);

            foreach ($studentsToEnroll as $student) {
                $this->enrollStudent($student, $course);
            }
        });
    }

    public function getEligibleStudents(string $gender): \Illuminate\Support\Collection
    {
        $level = Level::where(function ($q) use ($gender) {
            if ($gender === 'male') {
                $q->where('military_required_for_males', true);
            } else {
                $q->where('military_required_for_females', true);
            }
        })->first();

        if (! $level) {
            return collect();
        }

        $firstTimeStudents = Student::where('gender', $gender)
            ->where('level_id', $level->id)
            ->where(function ($q) {
                $q->whereNull('military_education_passed')
                    ->orWhere('military_education_passed', false);
            })
            ->whereDoesntHave('militaryEducationEnrollments')
            ->orderBy('id')
            ->get();

        $repeatStudents = Student::where('gender', $gender)
            ->whereHas('militaryEducationEnrollments', function ($q) {
                $q->where('status', 'failed')
                    ->orWhere('status', 'registered');
            })
            ->whereDoesntHave('militaryEducationEnrollments', function ($q) {
                $q->where('status', 'passed');
            })
            ->orderBy('id')
            ->get();

        return $repeatStudents->merge($firstTimeStudents);
    }

    public function enrollStudent(Student $student, MilitaryEducationCourse $course): void
    {
        $currentYear = Year::current();
        $currentSemester = Year::currentSemester();

        // Create enrollment
        MilitaryEducationEnrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'year_id' => $student->year_id ?? $currentYear?->id,
            'semester' => $student->semester?->value ?? $currentSemester?->value,
            'status' => MilitaryEducationEnrollmentStatus::REGISTERED,
        ]);

        // Create fee ticket
        $this->createFeeTicket($student, $course, $currentYear, $currentSemester);
    }

    public function createFeeTicket(Student $student, MilitaryEducationCourse $course, ?Year $year, $semester): void
    {
        $ticketNumber = date('ymdHis').$student->username;
        while (StudentFeeTicket::where('ticket_number', $ticketNumber)->exists()) {
            sleep(1);
            $ticketNumber = date('ymdHis').$student->username;
        }

        $yearLabel = $year?->year ?? '';
        $semesterLabel = $semester ? $semester->label() : '';

        StudentFeeTicket::create([
            'ticket_number' => $ticketNumber,
            'student_id' => $student->id,
            'fee_type' => 'additional', // Using 'additional' as a type for military ed fees
            'fee_id' => $course->id,
            'fee_name' => "مصاريف تربية عسكرية - {$course->name} - {$yearLabel} {$semesterLabel}",
            'amount' => $course->fee_amount,
            'status' => 'pending',
            'year_id' => $year?->id,
            'semester' => $semester?->value,
            'department_id' => $student->section?->department_id,
            'level_id' => $student->level_id,
            'section_id' => $student->section_id,
            'gender' => $course->gender,
        ]);
    }

    public function closeCourse(MilitaryEducationCourse $course): void
    {
        $course->update(['status' => 'closed']);
    }
}
