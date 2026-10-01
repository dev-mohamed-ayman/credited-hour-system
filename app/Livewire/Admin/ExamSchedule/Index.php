<?php

namespace App\Livewire\Admin\ExamSchedule;

use App\Enums\Semester;
use App\Exceptions\ExamScheduleException;
use App\Models\ExamCommittee;
use App\Models\Year;
use App\Services\ExamScheduleService;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $year_id = '';

    public $semester = '';

    public $status_filter = '';

    public $search = '';

    public bool $showUnassigned = false;

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

    public function updated($property): void
    {
        if (in_array($property, ['year_id', 'semester', 'status_filter', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function toggleUnassigned(): void
    {
        $this->showUnassigned = ! $this->showUnassigned;
    }

    public function publish($committeeId): void
    {
        abort_unless(auth()->user()->can('exam_schedules.publish'), 403);

        try {
            app(ExamScheduleService::class)->publish(ExamCommittee::findOrFail($committeeId));
        } catch (ExamScheduleException $e) {
            $this->dispatch('toast', ['message' => $e->getMessage(), 'type' => 'danger']);

            return;
        }

        $this->dispatch('toast', ['message' => 'تم نشر جدول اللجنة بنجاح', 'type' => 'success']);
    }

    public function unpublish($committeeId): void
    {
        abort_unless(auth()->user()->can('exam_schedules.publish'), 403);

        app(ExamScheduleService::class)->unpublish(ExamCommittee::findOrFail($committeeId));

        $this->dispatch('toast', ['message' => 'تم إخفاء جدول اللجنة عن الطلاب', 'type' => 'success']);
    }

    public function deleteCommittee($committeeId): void
    {
        abort_unless(auth()->user()->can('exam_schedules.delete'), 403);

        app(ExamScheduleService::class)->deleteCommittee(ExamCommittee::findOrFail($committeeId));

        $this->dispatch('toast', ['message' => 'تم حذف اللجنة بنجاح', 'type' => 'success']);
    }

    public function render()
    {
        abort_unless(auth()->user()->can('exam_schedules.view'), 403);

        $semesterEnum = $this->semester !== '' ? Semester::tryFrom($this->semester) : null;

        $committees = collect();
        $unassignedCount = 0;
        $unassigned = collect();

        if ($this->year_id && $semesterEnum !== null) {
            $committees = ExamCommittee::query()
                ->with('venue:id,name')
                ->withCount(['members', 'sessions'])
                ->withMin('sessions', 'exam_date')
                ->withMax('sessions', 'exam_date')
                ->where('year_id', $this->year_id)
                ->where('semester', $semesterEnum->value)
                ->when($this->status_filter !== '', fn ($query) => $query->where('status', $this->status_filter))
                ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('name', 'like', '%'.trim($this->search).'%')
                    ->orWhereHas('students', fn ($students) => $students->where('username', trim($this->search)))))
                ->orderBy('name')
                ->paginate(25);

            $unassignedQuery = app(ExamScheduleService::class)->unassignedStudentsQuery((int) $this->year_id, $semesterEnum);
            $unassignedCount = (clone $unassignedQuery)->count();

            if ($this->showUnassigned) {
                $unassigned = $unassignedQuery->orderBy('username')->limit(300)->get(['id', 'name', 'username']);
            }
        }

        return view('livewire.admin.exam-schedule.index', [
            'years' => Year::latest('id')->get(),
            'semesters' => Semester::cases(),
            'committees' => $committees,
            'unassignedCount' => $unassignedCount,
            'unassigned' => $unassigned,
        ])->extends('admin.layouts.app')->section('content');
    }
}
