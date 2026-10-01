<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamCommittee;
use App\Models\ExamSession;
use App\Models\Student;
use App\Models\Year;
use App\Services\ExamScheduleService;
use Illuminate\View\View;

class ExamPrintController extends Controller
{
    /**
     * Committee sheet: its exam timetable plus the full student list.
     */
    public function committeeSheet(ExamCommittee $committee): View
    {
        abort_unless(auth()->user()->can('exam_schedules.view'), 403);

        $committee->load(['venue:id,name', 'year:id,year']);

        $sessions = $committee->sessions()
            ->with('course:id,name')
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        $members = $committee->members()
            ->with(['student:id,name,username,section_id', 'student.section:id,name'])
            ->orderBy('seat_number')
            ->get();

        return view('admin.pages.exam.committee-sheet', compact('committee', 'sessions', 'members'));
    }

    /**
     * Attendance sheet for one exam: only the committee members sitting it.
     */
    public function sessionSheet(ExamSession $session): View
    {
        abort_unless(auth()->user()->can('exam_schedules.view'), 403);

        $session->load(['course:id,name', 'committee.venue:id,name', 'committee.year:id,year']);

        $members = app(ExamScheduleService::class)->examinees($session);

        return view('admin.pages.exam.session-sheet', compact('session', 'members'));
    }

    public function studentSchedule(Student $student): View
    {
        abort_unless(auth()->user()->can('students.view'), 403);

        $year = Year::current();
        $semester = $year?->getCurrentSemester();

        $timetable = $year !== null && $semester !== null
            ? app(ExamScheduleService::class)->studentTimetable($student, $year, $semester)
            : ['membership' => null, 'sessions' => collect(), 'unscheduled' => collect()];

        return view('admin.pages.exam.student-schedule', [
            'student' => $student,
            'year' => $year,
            'semester' => $semester,
            'membership' => $timetable['membership'],
            'sessions' => $timetable['sessions'],
        ]);
    }
}
