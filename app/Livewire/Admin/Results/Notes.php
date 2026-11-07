<?php

namespace App\Livewire\Admin\Results;

use App\Enums\Semester;
use App\Models\StudentTermNote;
use App\Models\Year;
use App\Services\ResultUploadService;
use App\Services\TermNoteUploadService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Uploads the control's per-term student notes from a sheet (كود الطالب، الملاحظة):
 * checked in full and previewed first, written only on confirmation.
 */
class Notes extends Component
{
    use WithFileUploads;

    public ?int $yearId = null;

    public ?string $semester = null;

    public ?TemporaryUploadedFile $sheet = null;

    /**
     * @var array{errors: array<int, array{row: int, message: string}>, rows: array<int, array{student_id: int, code: string, name: string, note: string|null}>}|null
     */
    public ?array $analysis = null;

    public function mount(): void
    {
        $this->yearId = Year::current()?->id;
        $this->semester = Year::currentSemester()?->value;
    }

    public function updated(): void
    {
        $this->analysis = null;
    }

    public function analyze(TermNoteUploadService $notes, ResultUploadService $sheets): void
    {
        abort_unless(auth()->user()->can('results.notes'), 403);

        $this->analysis = $this->runAnalysis($notes, $sheets);
    }

    public function confirm(TermNoteUploadService $notes, ResultUploadService $sheets): void
    {
        abort_unless(auth()->user()->can('results.notes'), 403);

        $analysis = $this->runAnalysis($notes, $sheets);

        if ($analysis === null || $analysis['errors'] !== [] || $analysis['rows'] === []) {
            $this->analysis = $analysis;
            $this->dispatch('toast', ['type' => 'error', 'message' => 'لم يتم حفظ الملاحظات، راجع الأخطاء الموضحة.']);

            return;
        }

        $count = $notes->save($analysis['rows'], Year::findOrFail($this->yearId), Semester::from($this->semester), auth()->user());

        $this->analysis = null;
        $this->reset('sheet');
        $this->dispatch('toast', ['type' => 'success', 'message' => "تم حفظ ملاحظات {$count} طالب."]);
    }

    public function delete(int $noteId): void
    {
        abort_unless(auth()->user()->can('results.notes'), 403);

        StudentTermNote::whereKey($noteId)->delete();
    }

    /**
     * @return array{errors: array<int, array{row: int, message: string}>, rows: array<int, array<string, mixed>>}|null
     */
    private function runAnalysis(TermNoteUploadService $notes, ResultUploadService $sheets): ?array
    {
        $this->validate([
            'yearId' => ['required', 'exists:years,id'],
            'semester' => ['required', 'in:'.implode(',', array_column(Semester::cases(), 'value'))],
            'sheet' => ['required', 'file', 'extensions:xlsx,csv', 'max:10240'],
        ], [
            'yearId.required' => 'اختر السنة الدراسية.',
            'semester.required' => 'اختر الترم.',
            'sheet.required' => 'اختر ملف الملاحظات.',
            'sheet.extensions' => 'يجب أن يكون الملف بصيغة xlsx أو csv.',
            'sheet.max' => 'حجم الملف أكبر من 10 ميجا.',
        ]);

        try {
            $rows = $sheets->readSheet($this->sheet->getRealPath(), $this->sheet->getClientOriginalExtension());
        } catch (RuntimeException $exception) {
            $this->addError('sheet', $exception->getMessage());

            return null;
        }

        return $notes->analyze($rows);
    }

    public function render(): View
    {
        return view('livewire.admin.results.notes', [
            'years' => Year::query()->orderByDesc('year')->get(),
            'semesters' => Semester::cases(),
            'notes' => $this->yearId && $this->semester
                ? StudentTermNote::query()->with('student:id,username,name')->where('year_id', $this->yearId)->where('semester', $this->semester)->latest('id')->get()
                : collect(),
        ])->extends('admin.layouts.app')->section('content');
    }
}
