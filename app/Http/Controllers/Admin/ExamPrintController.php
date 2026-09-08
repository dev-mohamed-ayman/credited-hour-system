<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExamSessionStatus;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\ExamCommittee;
use App\Models\ExamSession;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Year;

class ExamPrintController extends Controller
{
    public function committeeSheet(ExamCommittee $committee)
    {
        abort_unless(auth()->user()->can('exam_schedules.view'), 403);

        $committee->load(['venue:id,name', 'examSession.course:id,name', 'examSession.year:id,year']);

        $assignments = $committee->assignments()
            ->with(['student:id,name,username,section_id', 'student.section:id,name'])
            ->orderByRaw('CAST(seat_number AS UNSIGNED)')
            ->get();

        return view('admin.pages.exam.committee-sheet', compact('committee', 'assignments'));
    }

    public function studentSchedule(Student $student)
    {
        abort_unless(auth()->user()->can('students.view'), 403);

        $year = Year::current();
        $semester = $year?->getCurrentSemester();

        $sessions = collect();

        if ($year !== null && $semester !== null) {
            $courseIds = Registration::query()
                ->where('student_id', $student->id)
                ->where('year_id', $year->id)
                ->where('semester', $semester->value)
                ->where('status', RegistrationStatus::APPROVED->value)
                ->with('courses.course:id')
                ->get()
                ->flatMap(fn (Registration $registration) => $registration->courses->pluck('course_id'))
                ->unique()
                ->values();

            $sessions = ExamSession::query()
                ->with([
                    'course:id,name',
                    'seatAssignments' => fn ($query) => $query
                        ->where('student_id', $student->id)
                        ->with('committee.venue:id,name'),
                ])
                ->whereIn('course_id', $courseIds->all())
                ->where('year_id', $year->id)
                ->where('semester', $semester->value)
                ->where('status', ExamSessionStatus::PUBLISHED->value)
                ->orderBy('exam_date')
                ->orderBy('start_time')
                ->get();
        }

        return view('admin.pages.exam.student-schedule', compact('student', 'sessions', 'year', 'semester'));
    }
}
