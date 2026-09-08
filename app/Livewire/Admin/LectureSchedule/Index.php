<?php

namespace App\Livewire\Admin\LectureSchedule;

use App\Models\Course;
use App\Models\Department;
use App\Models\LectureSchedule;
use App\Models\Level;
use App\Models\Section;
use App\Services\LectureScheduleService;
use Livewire\Component;

class Index extends Component
{
    public $department_id = '';

    public $level_id = '';

    public $semester = '';

    public $course_id = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('lecture_schedules.view'), 403);

        if (request()->filled('course')) {
            $this->course_id = (int) request()->integer('course');
        }
    }

    public function updatedDepartmentId(): void
    {
        $this->reset('level_id', 'course_id');
    }

    public function updatedLevelId(): void
    {
        $this->reset('course_id');
    }

    public function updatedSemester(): void
    {
        $this->reset('course_id');
    }

    public function deleteSession($scheduleId): void
    {
        abort_unless(auth()->user()->can('lecture_schedules.delete'), 403);

        $schedule = LectureSchedule::findOrFail($scheduleId);
        $schedule->delete();

        $this->dispatch('toast', ['message' => 'تم حذف جلسة المحاضرة بنجاح', 'type' => 'success']);
    }

    public function render()
    {
        $courses = Course::query()
            ->with(['department', 'level'])
            ->when($this->department_id, fn ($query) => $query->where('department_id', $this->department_id))
            ->when($this->level_id, fn ($query) => $query->where('level_id', $this->level_id))
            ->when($this->semester, fn ($query) => $query->where('semester', $this->semester))
            ->orderBy('name')
            ->get();

        $selectedCourse = $this->course_id ? Course::find($this->course_id) : null;

        $sessions = collect();
        $flags = [];

        if ($selectedCourse !== null) {
            $sessions = LectureSchedule::with(['venue', 'sections'])
                ->where('course_id', $selectedCourse->id)
                ->get()
                ->sortBy(fn (LectureSchedule $schedule) => [$schedule->day->order(), $schedule->start_time])
                ->values();

            $service = app(LectureScheduleService::class);
            $linkedSectionIds = $selectedCourse->sections()->pluck('sections.id')->all();

            foreach ($sessions as $session) {
                $sessionSectionIds = $session->sections->pluck('id')->all();
                $total = $service->selectedStudentsCount($selectedCourse, $sessionSectionIds);

                $flags[$session->id] = [
                    'total' => $total,
                    'over_capacity' => $session->venue->capacity !== null && $total > $session->venue->capacity,
                    'orphans' => $session->sections
                        ->reject(fn (Section $section) => in_array($section->id, $linkedSectionIds, true))
                        ->pluck('name')
                        ->all(),
                ];
            }
        }

        return view('livewire.admin.lecture-schedule.index', [
            'courses' => $courses,
            'departments' => Department::all(),
            'levels' => Level::orderBy('id')->get(),
            'semesters' => ['الأول', 'الثاني', 'الصيفي'],
            'selectedCourse' => $selectedCourse,
            'sessions' => $sessions,
            'flags' => $flags,
        ])->extends('admin.layouts.app')->section('content');
    }
}
