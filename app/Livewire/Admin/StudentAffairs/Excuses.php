<?php

namespace App\Livewire\Admin\StudentAffairs;

use App\Enums\Semester;
use App\Enums\Student\ExcuseType;
use App\Exceptions\StudentExcuseException;
use App\Models\Student;
use App\Models\StudentExcuse;
use App\Models\Year;
use App\Services\StudentExcuseService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Per-term excuses and year suspensions (chs: add.excuses.index), read by the
 * end-of-term procedures.
 */
class Excuses extends Component
{
    public string $studentCode = '';

    public ?int $studentId = null;

    public ?int $yearId = null;

    public ?string $semester = null;

    public string $type = '';

    public string $reason = '';

    /** @var array<int, int|string> */
    public array $courseIds = [];

    public function mount(): void
    {
        $this->yearId = Year::current()?->id;
        $this->semester = Year::currentSemester()?->value;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['yearId', 'semester', 'type'], true)) {
            $this->courseIds = [];
        }
    }

    public function search(): void
    {
        $this->validate(['studentCode' => 'required|string'], ['studentCode.required' => 'اكتب كود الطالب.']);

        $this->studentId = Student::query()->where('username', trim($this->studentCode))->value('id');
        $this->courseIds = [];

        if ($this->studentId === null) {
            $this->addError('studentCode', 'لا يوجد طالب بهذا الكود.');
        }
    }

    public function add(StudentExcuseService $service): void
    {
        abort_unless(auth()->user()->can('student_excuses.create'), 403);

        $student = Student::findOrFail($this->studentId);

        $this->validate([
            'yearId' => 'required|exists:years,id',
            'semester' => ['required', Rule::enum(Semester::class)],
            'type' => ['required', Rule::enum(ExcuseType::class)],
            'reason' => 'required|string|max:255',
            'courseIds' => 'array',
            'courseIds.*' => 'integer',
        ], [
            'yearId.required' => 'اختر السنة الدراسية.',
            'semester.required' => 'اختر الترم.',
            'type.required' => 'اختر نوع العذر.',
            'reason.required' => 'اكتب سبب العذر.',
            'reason.max' => 'السبب لا يزيد عن 255 حرف.',
        ]);

        try {
            $service->add(
                $student,
                Year::findOrFail($this->yearId),
                Semester::from($this->semester),
                ExcuseType::from($this->type),
                $this->reason,
                array_map('intval', $this->courseIds),
                auth()->user(),
            );
        } catch (StudentExcuseException $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $this->reset(['type', 'reason', 'courseIds']);
        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم تسجيل العذر بنجاح.']);
    }

    public function remove(int $excuseId, StudentExcuseService $service): void
    {
        abort_unless(auth()->user()->can('student_excuses.delete'), 403);

        try {
            $service->remove(StudentExcuse::where('student_id', $this->studentId)->findOrFail($excuseId));
        } catch (StudentExcuseException $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم حذف العذر ورجوع المواد Pending.']);
    }

    public function render(): View
    {
        $student = $this->studentId
            ? Student::with(['level', 'section.department'])->find($this->studentId)
            : null;

        $year = $this->yearId ? Year::find($this->yearId) : null;
        $semester = $this->semester ? Semester::tryFrom($this->semester) : null;

        return view('livewire.admin.student-affairs.excuses', [
            'student' => $student,
            'excuses' => $student?->excuses()->with(['year', 'createdBy'])->latest('id')->get() ?? collect(),
            'termCourses' => $student && $year && $semester
                ? app(StudentExcuseService::class)->termCourses($student, $year, $semester)
                : collect(),
            'years' => Year::query()->orderByDesc('year')->get(),
            'semesters' => [Semester::FIRST, Semester::SECOND],
            'types' => ExcuseType::cases(),
        ])->extends('admin.layouts.app')->section('content');
    }
}
