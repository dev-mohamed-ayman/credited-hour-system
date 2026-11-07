<?php

namespace App\Livewire\Admin\Results;

use App\Enums\ExamResultStatus;
use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Models\Grade;
use App\Models\RegistrationCourse;
use App\Models\Student;
use App\Models\Year;
use App\Services\ResultUploadService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Corrects one student's result by hand, with a reason, without re-uploading the
 * whole course sheet. Every change lands in the results change log.
 */
class EditResult extends Component
{
    public string $studentCode = '';

    public ?int $studentId = null;

    public ?int $yearId = null;

    public ?string $semester = null;

    public ?int $enrollmentId = null;

    public string $written = '';

    public string $coursework = '';

    public string $total = '';

    public string $grade = '';

    public string $note = '';

    public string $reason = '';

    /** @var array<int, string> */
    public array $resultErrors = [];

    public function mount(): void
    {
        $this->yearId = Year::current()?->id;
        $this->semester = Year::currentSemester()?->value;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['yearId', 'semester'], true)) {
            $this->closeForm();
        }
    }

    public function search(): void
    {
        $this->validate(['studentCode' => 'required|string'], ['studentCode.required' => 'اكتب كود الطالب.']);

        $this->studentId = Student::query()->where('username', trim($this->studentCode))->value('id');
        $this->closeForm();

        if ($this->studentId === null) {
            $this->addError('studentCode', 'لا يوجد طالب بهذا الكود.');
        }
    }

    public function edit(int $enrollmentId): void
    {
        $enrollment = $this->enrollment($enrollmentId);
        $isPending = $enrollment->grade?->is_pending_default ?? true;

        $this->enrollmentId = $enrollment->id;
        $this->written = $this->mark($enrollment->written_score)
            ?? ($enrollment->exam_status && $enrollment->exam_status !== ExamResultStatus::CANCELLED ? $enrollment->exam_status->label() : '');
        $this->coursework = $this->mark($enrollment->coursework_score) ?? '';
        $this->total = $this->mark($enrollment->total_score) ?? (string) $enrollment->exam_status?->label();
        $this->grade = $isPending ? '' : (string) $enrollment->grade?->name;
        $this->note = (string) $enrollment->result_note;
        $this->reason = '';
        $this->resultErrors = [];
    }

    public function save(ResultUploadService $service): void
    {
        abort_unless(auth()->user()->can('results.edit'), 403);

        $enrollment = $this->enrollment((int) $this->enrollmentId);

        $this->resultErrors = $service->editResult(
            $enrollment,
            $this->written,
            $this->coursework,
            $this->total,
            $this->grade,
            $this->note,
            $this->reason,
            auth()->user(),
        );

        if ($this->resultErrors !== []) {
            return;
        }

        $this->closeForm();
        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم تعديل النتيجة وتسجيل التعديل في السجل.']);
    }

    public function closeForm(): void
    {
        $this->reset(['enrollmentId', 'written', 'coursework', 'total', 'grade', 'note', 'reason', 'resultErrors']);
    }

    private function enrollment(int $enrollmentId): RegistrationCourse
    {
        return $this->termEnrollments()->with('grade')->findOrFail($enrollmentId);
    }

    private function termEnrollments(): Builder
    {
        return RegistrationCourse::query()->whereHas('registration', fn (Builder $registration) => $registration
            ->where('student_id', $this->studentId ?? 0)
            ->where('year_id', $this->yearId ?? 0)
            ->where('semester', (string) $this->semester)
            ->where('status', RegistrationStatus::APPROVED->value));
    }

    private function mark(mixed $value): ?string
    {
        return $value === null ? null : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    public function render(): View
    {
        $student = $this->studentId ? Student::with(['level', 'section.department'])->find($this->studentId) : null;

        return view('livewire.admin.results.edit-result', [
            'student' => $student,
            'enrollments' => $student ? $this->termEnrollments()->with(['course', 'grade', 'resultUploadedBy'])->get() : collect(),
            'history' => $this->enrollmentId
                ? RegistrationCourse::find($this->enrollmentId)?->resultChanges()->with('user')->latest('id')->get() ?? collect()
                : collect(),
            'years' => Year::query()->orderByDesc('year')->get(),
            'semesters' => Semester::cases(),
            'grades' => Grade::query()->where('is_pending_default', false)->orderBy('order')->get(),
        ])->extends('admin.layouts.app')->section('content');
    }
}
