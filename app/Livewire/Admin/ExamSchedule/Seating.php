<?php

namespace App\Livewire\Admin\ExamSchedule;

use App\Exceptions\ExamScheduleException;
use App\Models\ExamCommittee;
use App\Models\ExamSeatAssignment;
use App\Models\ExamSession;
use App\Services\ExamScheduleService;
use App\Services\ExamSeatingService;
use Livewire\Component;
use Livewire\WithPagination;

class Seating extends Component
{
    use WithPagination;

    public ExamSession $session;

    public $activeCommitteeId = '';

    public $moveTarget = [];

    public function mount(ExamSession $session): void
    {
        abort_unless(auth()->user()->can('exam_schedules.edit'), 403);

        $this->session = $session;
        $this->activeCommitteeId = (string) ($session->committees()->orderBy('id')->value('id') ?? '');
    }

    public function selectCommittee($committeeId): void
    {
        $this->activeCommitteeId = (string) $committeeId;
        $this->resetPage();
    }

    public function generate(): void
    {
        abort_unless(auth()->user()->can('exam_schedules.edit'), 403);

        try {
            app(ExamSeatingService::class)->generateDistribution($this->session);
        } catch (ExamScheduleException $e) {
            $this->dispatch('toast', ['message' => $e->getMessage(), 'type' => 'danger']);

            return;
        }

        $this->dispatch('toast', ['message' => 'تم توليد التوزيع بنجاح', 'type' => 'success']);
    }

    public function move($assignmentId): void
    {
        abort_unless(auth()->user()->can('exam_schedules.edit'), 403);

        $targetId = $this->moveTarget[$assignmentId] ?? null;

        if (! $targetId) {
            $this->dispatch('toast', ['message' => 'اختر اللجنة الهدف أولًا', 'type' => 'danger']);

            return;
        }

        $assignment = ExamSeatAssignment::where('exam_session_id', $this->session->id)->findOrFail($assignmentId);

        try {
            app(ExamSeatingService::class)->moveStudent($assignment, ExamCommittee::findOrFail($targetId));
        } catch (ExamScheduleException $e) {
            $this->dispatch('toast', ['message' => $e->getMessage(), 'type' => 'danger']);

            return;
        }

        unset($this->moveTarget[$assignmentId]);

        $this->dispatch('toast', ['message' => 'تم نقل الطالب بنجاح', 'type' => 'success']);
    }

    public function render()
    {
        $seating = app(ExamSeatingService::class);

        $committees = $this->session->committees()
            ->with('venue:id,name')
            ->withCount('assignments')
            ->orderBy('id')
            ->get();

        if ($this->activeCommitteeId === '' && $committees->isNotEmpty()) {
            $this->activeCommitteeId = (string) $committees->first()->id;
        }

        $active = $committees->firstWhere('id', (int) $this->activeCommitteeId);

        $assignments = $active
            ? $this->session->seatAssignments()
                ->where('exam_committee_id', $active->id)
                ->with(['student:id,name,username,section_id', 'student.section:id,name'])
                ->orderByRaw('CAST(seat_number AS UNSIGNED)')
                ->paginate(25, ['*'], 'assignmentsPage')
            : collect();

        return view('livewire.admin.exam-schedule.seating', [
            'committees' => $committees,
            'active' => $active,
            'assignments' => $assignments,
            'examineeCount' => app(ExamScheduleService::class)->examineeCount($this->session),
            'totalCapacity' => $seating->totalCapacity($this->session),
            'stale' => $seating->isSeatingStale($this->session),
        ])->extends('admin.layouts.app')->section('content');
    }
}
