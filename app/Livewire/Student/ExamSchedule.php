<?php

namespace App\Livewire\Student;

use App\Enums\ExamSessionStatus;
use App\Enums\RegistrationStatus;
use App\Models\Course;
use App\Models\ExamSession;
use App\Models\Registration;
use App\Models\Year;
use Livewire\Component;

class ExamSchedule extends Component
{
    public function render()
    {
        $student = auth()->guard('student')->user();
        $year = Year::current();
        $semester = $year?->getCurrentSemester();

        $sessions = collect();
        $unscheduled = collect();

        if ($student !== null && $year !== null && $semester !== null) {
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

            // "Not scheduled yet" hints only appear once the term's schedule
            // has been made visible at all — zero leakage before first publish.
            $unscheduled = $sessions->isNotEmpty()
                ? Course::query()
                    ->whereIn('id', $courseIds->diff($sessions->pluck('course_id'))->all())
                    ->orderBy('name')
                    ->get()
                : collect();
        }

        $view = view('livewire.student.exam-schedule', [
            'sessions' => $sessions,
            'unscheduled' => $unscheduled,
            'semester' => $semester,
        ]);

        return request()->routeIs('student.exam-schedule')
            ? $view->extends('student.layouts.app')->section('content')
            : $view;
    }
}
