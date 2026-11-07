<?php

namespace App\Livewire\Admin\Results;

use App\Enums\Semester;
use App\Enums\TermOutcome;
use App\Exceptions\TermClosureException;
use App\Models\Level;
use App\Models\Setting;
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
     * @var array<int, array{student_id: int, code: string, name: string, level: string|null, level_after: string|null, cgpa: float, earned_hours: int, outcome: string, note: string|null, warnings_before: int, warnings_after: int, study_status: string|null, study_status_after: string|null}>|null
     */
    public ?array $rows = null;

    public ?int $viewingClosureId = null;

    /** @var array<int, int|string|null> level_id => earned hours to start the level */
    public array $levelStartHours = [];

    public int|string $graduationHours = '';

    public float|string $warningThreshold = '';

    public int|string $exemptHours = '';

    public int|string $dismissalWarnings = '';

    public int|string $maxYearsInLevel = '';

    public function mount(): void
    {
        $this->yearId = Year::current()?->id;
        $this->semester = Year::currentSemester()?->value;
        $this->loadRules();
    }

    public function saveRules(): void
    {
        abort_unless(auth()->user()->can('term_closures.settings'), 403);

        $levels = Level::query()->orderBy('id')->get();
        $laterLevelIds = $levels->slice(1)->pluck('id');

        $this->validate([
            ...$laterLevelIds->mapWithKeys(fn (int $id) => ["levelStartHours.{$id}" => 'required|integer|min:1|max:500'])->all(),
            'graduationHours' => 'required|integer|min:1|max:500',
            'warningThreshold' => 'required|numeric|min:0|max:4',
            'exemptHours' => 'required|integer|min:1|max:500',
            'dismissalWarnings' => 'required|integer|min:1|max:10',
            'maxYearsInLevel' => 'required|integer|min:1|max:20',
        ], [
            'levelStartHours.*.required' => 'اكتب ساعات بداية الفرقة.',
            'levelStartHours.*.integer' => 'لازم تكون رقم صحيح.',
            'levelStartHours.*.min' => 'لازم تكون ساعة واحدة على الأقل.',
            'graduationHours.required' => 'اكتب ساعات التخرج.',
            'warningThreshold.required' => 'اكتب حد الإنذار.',
            'warningThreshold.max' => 'المعدل من 4.',
            'exemptHours.required' => 'اكتب الساعات المعفية من الإنذار.',
            'dismissalWarnings.required' => 'اكتب عدد الإنذارات اللي بتفصل.',
            'maxYearsInLevel.required' => 'اكتب أقصى عدد سنين في الفرقة.',
        ]);

        $previous = 0;

        foreach ($laterLevelIds as $id) {
            if ((int) $this->levelStartHours[$id] <= $previous) {
                $this->addError("levelStartHours.{$id}", 'لازم تكون أكبر من ساعات بداية الفرقة اللي قبلها.');

                return;
            }

            $previous = (int) $this->levelStartHours[$id];
        }

        if ((int) $this->graduationHours <= $previous) {
            $this->addError('graduationHours', 'ساعات التخرج لازم تكون أكبر من ساعات بداية آخر فرقة.');

            return;
        }

        foreach ($levels as $index => $level) {
            $level->update(['start_hours' => $index === 0 ? null : (int) $this->levelStartHours[$level->id]]);
        }

        Setting::query()->firstOrCreate([])->update([
            'graduation_required_hours' => (int) $this->graduationHours,
            'warning_gpa_threshold' => round((float) $this->warningThreshold, 2),
            'warning_exempt_earned_hours' => (int) $this->exemptHours,
            'dismissal_warnings' => (int) $this->dismissalWarnings,
            'max_years_in_level' => (int) $this->maxYearsInLevel,
        ]);

        $this->rows = null;
        $this->viewingClosureId = null;
        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم حفظ إعدادات الترقية والإنذارات. اعمل محاكاة تاني قبل التنفيذ.']);
    }

    private function loadRules(): void
    {
        $rules = app(TermClosureService::class)->rules();

        $this->levelStartHours = Level::query()->orderBy('id')->pluck('start_hours', 'id')->all();
        $this->graduationHours = $rules['graduation_hours'];
        $this->warningThreshold = $rules['threshold'];
        $this->exemptHours = $rules['exempt_hours'];
        $this->dismissalWarnings = $rules['dismissal_warnings'];
        $this->maxYearsInLevel = $rules['max_years_in_level'];
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
                'student_id' => $decision['student']->id,
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
                'student_id' => $record->student_id,
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
            'levels' => Level::query()->orderBy('id')->get(),
            'rules' => $service->rules(),
            'counts' => $rows->countBy('outcome'),
            'visibleRows' => $this->outcomeFilter === '' ? $rows : $rows->where('outcome', $this->outcomeFilter),
            'closures' => TermClosure::query()->with(['year', 'closedBy'])->withCount('records')->latest('id')->get(),
            'viewingClosure' => $this->viewingClosureId ? TermClosure::with('year')->find($this->viewingClosureId) : null,
        ])->extends('admin.layouts.app')->section('content');
    }
}
