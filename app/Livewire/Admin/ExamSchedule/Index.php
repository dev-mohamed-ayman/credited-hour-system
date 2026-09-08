<?php

namespace App\Livewire\Admin\ExamSchedule;

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Models\Course;
use App\Models\Department;
use App\Models\ExamSession;
use App\Models\Level;
use App\Models\RegistrationCourse;
use App\Models\Year;
use App\Services\ExamScheduleService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $year_id = '';

    public $semester = '';

    public $department_id = '';

    public $level_id = '';

    public $status_filter = '';

    public $sort = 'name';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('exam_schedules.view'), 403);

        $this->year_id = Year::current()?->id ?? '';
        $this->semester = Year::currentSemester()?->value ?? '';

        if (request()->filled('year')) {
            $this->year_id = request()->integer('year');
        }

        if (request()->filled('semester')) {
            $this->semester = (string) request()->query('semester');
        }
    }

    public function updatedYearId(): void
    {
        $this->resetPage();
    }

    public function updatedSemester(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentId(): void
    {
        $this->resetPage();
    }

    public function updatedLevelId(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function publish($sessionId): void
    {
        abort_unless(auth()->user()->can('exam_schedules.publish'), 403);

        $session = ExamSession::findOrFail($sessionId);

        try {
            app(ExamScheduleService::class)->publish($session);
        } catch (\App\Exceptions\ExamScheduleException $e) {
            $this->dispatch('toast', ['message' => $e->getMessage(), 'type' => 'danger']);

            return;
        }

        $this->dispatch('toast', ['message' => 'تم نشر جلسة الامتحان بنجاح', 'type' => 'success']);
    }

    public function unpublish($sessionId): void
    {
        abort_unless(auth()->user()->can('exam_schedules.publish'), 403);

        $session = ExamSession::findOrFail($sessionId);
        $session->update(['status' => \App\Enums\ExamSessionStatus::DRAFT]);

        $this->dispatch('toast', ['message' => 'تم إخفاء جدول الامتحان عن الطلاب', 'type' => 'success']);
    }

    public function deleteSession($sessionId): void
    {
        abort_unless(auth()->user()->can('exam_schedules.delete'), 403);

        $session = ExamSession::with(['committees', 'seatAssignments'])->findOrFail($sessionId);

        if ($message = $session->getBlockingRelationsMessage()) {
            $this->dispatch('toast', ['message' => $message, 'type' => 'danger']);

            return;
        }

        $session->delete();

        $this->dispatch('toast', ['message' => 'تم حذف جلسة الامتحان بنجاح', 'type' => 'success']);
    }

    /**
     * Correlated sub-select of approved examinees per course for sorting (T047).
     */
    private function examineesSortExpr(int $yearId, Semester $semester): \Illuminate\Database\Query\Builder
    {
        return RegistrationCourse::query()
            ->select(DB::raw('COUNT(DISTINCT registrations.student_id)'))
            ->join('registrations', 'registrations.id', '=', 'registration_courses.registration_id')
            ->join('students', 'students.id', '=', 'registrations.student_id')
            ->whereColumn('registration_courses.course_id', 'courses.id')
            ->whereNull('students.deleted_at')
            ->where('registrations.year_id', $yearId)
            ->where('registrations.semester', $semester->value)
            ->where('registrations.status', RegistrationStatus::APPROVED->value)
            ->toBase();
    }

    /**
     * Correlated sub-select of the course's earliest session field for sorting.
     */
    private function sessionFieldSortExpr(string $field, int $yearId, Semester $semester): \Illuminate\Database\Query\Builder
    {
        return ExamSession::query()
            ->select($field)
            ->whereColumn('exam_sessions.course_id', 'courses.id')
            ->where('exam_sessions.year_id', $yearId)
            ->where('exam_sessions.semester', $semester->value)
            ->orderBy($field)
            ->limit(1)
            ->toBase();
    }

    public function render()
    {
        abort_unless(auth()->user()->can('exam_schedules.view'), 403);

        $years = Year::latest('id')->get();
        $semesterEnum = $this->semester !== '' ? Semester::tryFrom($this->semester) : null;
        $service = app(ExamScheduleService::class);

        $courses = collect();
        $sessions = collect();
        $counts = [];

        if ($this->year_id && $semesterEnum !== null) {
            $courses = Course::query()
                ->with(['department:id,name', 'level:id,name'])
                ->whereIn('id', $service->audienceCourseIds((int) $this->year_id, $semesterEnum))
                ->when($this->department_id, fn ($query) => $query->where('department_id', $this->department_id))
                ->when($this->level_id, fn ($query) => $query->where('level_id', $this->level_id))
                ->when($this->sort === 'examinees', fn ($query) => $query->orderByDesc($this->examineesSortExpr((int) $this->year_id, $semesterEnum)))
                ->when($this->sort === 'exam_date', fn ($query) => $query->orderBy($this->sessionFieldSortExpr('exam_date', (int) $this->year_id, $semesterEnum)))
                ->when($this->sort === 'status', fn ($query) => $query->orderBy($this->sessionFieldSortExpr('status', (int) $this->year_id, $semesterEnum)))
                ->orderBy('name')
                ->paginate(25);

            $sessions = ExamSession::query()
                ->with('committees')
                ->where('year_id', $this->year_id)
                ->where('semester', $semesterEnum->value)
                ->whereIn('course_id', $courses->pluck('id')->all())
                ->get()
                ->keyBy('course_id');

            $counts = $service->audienceCounts((int) $this->year_id, $semesterEnum);

            $seating = app(\App\Services\ExamSeatingService::class);
            $stale = $sessions->mapWithKeys(
                fn (ExamSession $session) => [$session->id => $session->seatAssignments()->exists() && $seating->isSeatingStale($session)],
            );
        }

        if ($this->status_filter !== '') {
            $courses = $courses->filter(function (Course $course) use ($sessions) {
                $session = $sessions->get($course->id);

                return match ($this->status_filter) {
                    'not_set' => $session === null,
                    default => $session?->status?->value === $this->status_filter,
                };
            })->values();
        }

        return view('livewire.admin.exam-schedule.index', [
            'years' => $years,
            'semesters' => Semester::cases(),
            'departments' => Department::all(),
            'levels' => Level::orderBy('id')->get(),
            'courses' => $courses,
            'sessions' => $sessions,
            'counts' => $counts,
            'stale' => $stale ?? collect(),
        ])->extends('admin.layouts.app')->section('content');
    }
}
