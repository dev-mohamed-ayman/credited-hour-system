<?php

namespace App\Livewire\Admin\Results;

use App\Enums\Semester;
use App\Models\Course;
use App\Models\Department;
use App\Models\Year;
use App\Services\ResultUploadService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Two-step result upload: the sheet is first checked in full and previewed,
 * then written only on confirmation (re-checked against the database again).
 */
class Upload extends Component
{
    use WithFileUploads;

    public ?int $yearId = null;

    public ?string $semester = null;

    public ?int $departmentId = null;

    public ?int $courseId = null;

    public ?TemporaryUploadedFile $sheet = null;

    /**
     * Outcome of the last check, shown as the preview.
     *
     * @var array{errors: array<int, array{row: int, message: string}>, rows: array<int, array<string, mixed>>, missing: array<int, array{code: string, name: string}>, overwritten: int}|null
     */
    public ?array $analysis = null;

    public ?int $savedCount = null;

    public function mount(): void
    {
        $this->yearId = Year::current()?->id;
        $this->semester = Year::currentSemester()?->value;
    }

    public function updated(string $property): void
    {
        if ($property === 'departmentId') {
            $this->courseId = null;
        }

        $this->analysis = null;
        $this->savedCount = null;
    }

    public function analyze(ResultUploadService $service): void
    {
        $this->authorizeUpload();
        $this->validateSelection();
        $this->savedCount = null;

        $this->analysis = $this->runAnalysis($service);
    }

    public function confirm(ResultUploadService $service): void
    {
        $this->authorizeUpload();
        $this->validateSelection();

        $analysis = $this->runAnalysis($service);

        if ($analysis === null || $analysis['errors'] !== [] || $analysis['rows'] === []) {
            $this->analysis = $analysis;
            $this->dispatch('toast', ['type' => 'error', 'message' => 'لم يتم حفظ النتيجة، راجع الأخطاء الموضحة.']);

            return;
        }

        $this->savedCount = $service->save($analysis['rows'], auth()->user());
        $this->analysis = null;
        $this->reset('sheet');

        $this->dispatch('toast', ['type' => 'success', 'message' => "تم رفع نتيجة {$this->savedCount} طالب بنجاح."]);
    }

    public function downloadTemplate(ResultUploadService $service): ?BinaryFileResponse
    {
        $this->authorizeUpload();
        $this->validate($this->selectionRules(), $this->messages());

        $course = Course::findOrFail($this->courseId);
        $path = sys_get_temp_dir().'/'.Str::uuid().'.xlsx';

        $service->writeTemplate($path, $course, Year::findOrFail($this->yearId), Semester::from($this->semester));

        return response()->download($path, "نتيجة {$course->code}.xlsx")->deleteFileAfterSend();
    }

    /**
     * @return array{errors: array<int, array{row: int, message: string}>, rows: array<int, array<string, mixed>>, missing: array<int, array{code: string, name: string}>, overwritten: int}|null
     */
    private function runAnalysis(ResultUploadService $service): ?array
    {
        $year = Year::findOrFail($this->yearId);
        $semester = Semester::from($this->semester);

        $blockReason = $service->termBlockReason($year, $semester);

        if ($blockReason) {
            $this->addError('semester', $blockReason);

            return null;
        }

        try {
            $sheetRows = $service->readSheet($this->sheet->getRealPath(), $this->sheet->getClientOriginalExtension());
        } catch (RuntimeException $exception) {
            $this->addError('sheet', $exception->getMessage());

            return null;
        }

        return $service->analyze($sheetRows, Course::findOrFail($this->courseId), $year, $semester);
    }

    private function validateSelection(): void
    {
        $this->validate([
            ...$this->selectionRules(),
            'sheet' => ['required', 'file', 'extensions:xlsx,csv', 'max:10240'],
        ], $this->messages());
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function selectionRules(): array
    {
        return [
            'yearId' => ['required', 'exists:years,id'],
            'semester' => ['required', 'in:'.implode(',', array_column(Semester::cases(), 'value'))],
            'courseId' => ['required', 'exists:courses,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'yearId.required' => 'اختر السنة الدراسية.',
            'semester.required' => 'اختر الترم.',
            'courseId.required' => 'اختر المادة.',
            'sheet.required' => 'اختر ملف النتيجة.',
            'sheet.extensions' => 'يجب أن يكون الملف بصيغة xlsx أو csv.',
            'sheet.max' => 'حجم الملف أكبر من 10 ميجا.',
        ];
    }

    private function authorizeUpload(): void
    {
        abort_unless(auth()->user()->can('results.upload'), 403);
    }

    public function render(): View
    {
        return view('livewire.admin.results.upload', [
            'years' => Year::query()->latest()->get(['id', 'year']),
            'semesters' => Semester::cases(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'courses' => Course::query()
                ->when($this->departmentId, fn (Builder $query) => $query->where('department_id', $this->departmentId))
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'selectedCourse' => $this->courseId ? Course::find($this->courseId) : null,
        ])
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
