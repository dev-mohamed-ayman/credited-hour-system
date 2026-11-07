<?php

namespace App\Livewire\Admin\Results;

use App\Enums\Semester;
use App\Enums\TermOutcome;
use App\Exceptions\TermClosureException;
use App\Models\StudentTermRecord;
use App\Models\TermClosure;
use App\Models\Year;
use App\Services\TermClosureService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * End-of-term procedures: simulate what closing the term would do to every
 * student, run it once, and revert the latest closure if something went wrong.
 */
class EndOfTerm extends Component
{
    public ?int $yearId = null;

    public ?string $semester = null;

    public string $outcomeFilter = '';

    /**
     * Rows of the simulation, or of the closure being viewed.
     *
     * @var array<int, array{code: string, name: string, level: string|null, level_after: string|null, cgpa: float, earned_hours: int, outcome: string, note: string|null, warnings_before: int, warnings_after: int, study_status: string|null, study_status_after: string|null}>|null
     */
    public ?array $rows = null;

    public ?int $viewingClosureId = null;

    public function mount(): void
    {
        $this->yearId = Year::current()?->id;
        $this->semester = Year::currentSemester()?->value;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['yearId', 'semester'], true)) {
            $this->rows = null;
            $this->viewingClosureId = null;
            $this->outcomeFilter = '';
        }
    }

    public function simulate(TermClosureService $service): void
    {
        abort_unless(auth()->user()->can('term_closures.view'), 403);

        [$year, $semester] = $this->term();

        $this->viewingClosureId = null;
        $this->outcomeFilter = '';
        $this->rows = $service->simulate($year, $semester)
            ->map(fn (array $decision) => [
                'code' => $decision['student']->username,
                'name' => $decision['student']->name,
                'level' => $decision['student']->level?->name,
                'level_after' => $decision['level_after']?->name,
                'cgpa' => $decision['cgpa'],
                'earned_hours' => $decision['earned_hours'],
                'outcome' => $decision['outcome']->value,
                'note' => $decision['note'],
                'warnings_before' => $decision['warnings_before'],
                'warnings_after' => $decision['warnings_after'],
                'study_status' => $decision['student']->study_status?->label(),
                'study_status_after' => $decision['study_status_after']?->label(),
            ])
            ->all();
    }

    public function execute(TermClosureService $service): void
    {
        abort_unless(auth()->user()->can('term_closures.execute'), 403);

        [$year, $semester] = $this->term();

        try {
            $closure = $service->close($year, $semester, auth()->user());
        } catch (TermClosureException $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $this->showClosure($closure->id);
        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم تنفيذ إجراءات نهاية الترم لعدد '.count($this->rows).' طالب.']);
    }

    public function revert(int $closureId, TermClosureService $service): void
    {
        abort_unless(auth()->user()->can('term_closures.revert'), 403);

        try {
            $service->revert(TermClosure::findOrFail($closureId));
        } catch (TermClosureException $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $this->rows = null;
        $this->viewingClosureId = null;
        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم التراجع عن إجراءات الترم وإرجاع بيانات الطلاب كما كانت.']);
    }

    public function showClosure(int $closureId): void
    {
        abort_unless(auth()->user()->can('term_closures.view'), 403);

        $closure = TermClosure::findOrFail($closureId);

        $this->viewingClosureId = $closure->id;
        $this->outcomeFilter = '';
        $this->rows = $closure->records()
            ->with(['student' => fn ($query) => $query->withTrashed(), 'levelBefore', 'levelAfter'])
            ->get()
            ->sortBy(fn (StudentTermRecord $record) => $record->student?->username)
            ->map(fn (StudentTermRecord $record) => [
                'code' => (string) $record->student?->username,
                'name' => (string) $record->student?->name,
                'level' => $record->levelBefore?->name,
                'level_after' => $record->levelAfter?->name,
                'cgpa' => (float) $record->cgpa,
                'earned_hours' => $record->earned_hours,
                'outcome' => $record->outcome->value,
                'note' => $record->note,
                'warnings_before' => $record->warnings_before,
                'warnings_after' => $record->warnings_after,
                'study_status' => $record->study_status_before?->label(),
                'study_status_after' => $record->study_status_after?->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{0: Year, 1: Semester}
     */
    private function term(): array
    {
        $this->validate(
            ['yearId' => 'required|exists:years,id', 'semester' => 'required|in:'.implode(',', array_column(Semester::cases(), 'value'))],
            ['yearId.required' => 'اختر السنة الدراسية.', 'semester.required' => 'اختر الترم.'],
        );

        return [Year::findOrFail($this->yearId), Semester::from($this->semester)];
    }

    public function render(): View
    {
        $service = app(TermClosureService::class);
        $year = $this->yearId ? Year::find($this->yearId) : null;
        $semester = $this->semester ? Semester::tryFrom($this->semester) : null;

        $rows = collect($this->rows ?? []);

        return view('livewire.admin.results.end-of-term', [
            'years' => Year::query()->orderByDesc('year')->get(),
            'semesters' => Semester::cases(),
            'outcomes' => TermOutcome::cases(),
            'blockers' => $year && $semester ? $service->blockers($year, $semester) : [],
            'isPromotion' => $semester === Semester::SUMMER,
            'counts' => $rows->countBy('outcome'),
            'visibleRows' => $this->outcomeFilter === '' ? $rows : $rows->where('outcome', $this->outcomeFilter),
            'closures' => TermClosure::query()->with(['year', 'closedBy'])->withCount('records')->latest('id')->get(),
            'viewingClosure' => $this->viewingClosureId ? TermClosure::with('year')->find($this->viewingClosureId) : null,
        ])->extends('admin.layouts.app')->section('content');
    }
}
