<?php

namespace App\Livewire\Admin\LectureSchedule;

use App\Enums\DayOfWeek;
use App\Models\Course;
use App\Models\LectureSchedule;
use Livewire\Component;

class WeekGrid extends Component
{
    public Course $course;

    public function mount(Course $course): void
    {
        abort_unless(auth()->user()->can('lecture_schedules.view'), 403);

        $this->course = $course;
    }

    public function render()
    {
        $sessions = LectureSchedule::with(['venue:id,name,type,capacity', 'sections:id,name'])
            ->where('course_id', $this->course->id)
            ->get();

        $days = collect(DayOfWeek::cases())->sortBy(fn (DayOfWeek $day) => $day->order())->values();

        $times = $sessions
            ->map(fn (LectureSchedule $session) => substr((string) $session->start_time, 0, 5))
            ->unique()
            ->sort()
            ->values();

        $cells = [];
        foreach ($sessions as $session) {
            $cells[$session->day->value][substr((string) $session->start_time, 0, 5)][] = $session;
        }

        return view('livewire.admin.lecture-schedule.week-grid', [
            'days' => $days,
            'times' => $times,
            'cells' => $cells,
        ])->extends('admin.layouts.app')->section('content');
    }
}
