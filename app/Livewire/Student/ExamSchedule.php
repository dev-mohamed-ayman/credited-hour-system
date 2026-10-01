<?php

namespace App\Livewire\Student;

use App\Models\Year;
use App\Services\ExamScheduleService;
use Livewire\Component;

class ExamSchedule extends Component
{
    public function render()
    {
        $student = auth()->guard('student')->user();
        $year = Year::current();
        $semester = $year?->getCurrentSemester();

        $timetable = $student !== null && $year !== null && $semester !== null
            ? app(ExamScheduleService::class)->studentTimetable($student, $year, $semester)
            : ['membership' => null, 'sessions' => collect(), 'unscheduled' => collect()];

        $view = view('livewire.student.exam-schedule', [
            'membership' => $timetable['membership'],
            'sessions' => $timetable['sessions'],
            'unscheduled' => $timetable['unscheduled'],
            'semester' => $semester,
        ]);

        return request()->routeIs('student.exam-schedule')
            ? $view->extends('student.layouts.app')->section('content')
            : $view;
    }
}
