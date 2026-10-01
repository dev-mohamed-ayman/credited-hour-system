<?php

namespace App\Livewire\Admin\ExamSchedule;

use App\Enums\ExamType;
use App\Exceptions\ExamScheduleException;
use App\Models\Course;
use App\Models\ExamCommittee;
use App\Services\ExamScheduleService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Manage extends Component
{
    use WithPagination;

    public ExamCommittee $committee;

    public $codeInput = '';

    /**
     * @var array<string, string> rejected code => reason
     */
    public array $codeErrors = [];

    public $memberSearch = '';

    public $editingSessionId = null;

    public $course_id = '';

    public $type = 'regular';

    public $exam_date = '';

    public $start_time = '';

    public $end_time = '';

    public $notes = '';

    protected function rules(): array
    {
        return [
            'course_id' => 'required|exists:courses,id',
            'type' => ['required', Rule::enum(ExamType::class)],
            'exam_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    protected function messages(): array
    {
        return [
            'course_id.required' => 'يجب اختيار المادة',
            'course_id.exists' => 'المادة المختارة غير موجودة',
            'type.required' => 'يجب اختيار نوع الامتحان',
            'type.enum' => 'نوع الامتحان المختار غير مسموح به',
            'exam_date.required' => 'تاريخ الامتحان مطلوب',
            'exam_date.date' => 'تاريخ الامتحان غير صحيح',
            'start_time.required' => 'وقت البداية مطلوب',
            'start_time.date_format' => 'تنسيق وقت البداية غير صحيح',
            'end_time.required' => 'وقت النهاية مطلوب',
            'end_time.date_format' => 'تنسيق وقت النهاية غير صحيح',
            'end_time.after' => 'يجب أن يكون وقت النهاية أكبر من وقت البداية',
        ];
    }

    public function mount(ExamCommittee $committee): void
    {
        abort_unless(auth()->user()->can('exam_schedules.view'), 403);

        $this->committee = $committee;
    }

    public function updatedMemberSearch(): void
    {
        $this->resetPage('membersPage');
    }

    /**
     * Adds every code typed/pasted in the tag input (separated by spaces,
     * commas or new lines) and keeps the rejected ones with their reasons.
     */
    public function addCodes(): void
    {
        abort_unless(auth()->user()->can('exam_schedules.edit'), 403);

        $codes = preg_split('/[\s,،;]+/u', (string) $this->codeInput, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($codes === []) {
            return;
        }

        $result = app(ExamScheduleService::class)->addStudentsByCodes($this->committee, $codes);

        $this->codeErrors = $result['errors'];
        $this->codeInput = implode(' ', array_keys($result['errors']));
        $this->committee->refresh();

        if ($result['added'] !== []) {
            $this->dispatch('toast', ['message' => 'تمت إضافة '.count($result['added']).' طالب', 'type' => 'success']);
        }
    }

    public function removeStudent($studentId): void
    {
        abort_unless(auth()->user()->can('exam_schedules.edit'), 403);

        app(ExamScheduleService::class)->removeStudent($this->committee, (int) $studentId);
        $this->committee->refresh();
    }

    public function clearCodeErrors(): void
    {
        $this->codeErrors = [];
        $this->codeInput = '';
    }

    public function editSession($sessionId): void
    {
        abort_unless(auth()->user()->can('exam_schedules.edit'), 403);

        $session = $this->committee->sessions()->findOrFail($sessionId);

        $this->editingSessionId = $session->id;
        $this->course_id = $session->course_id;
        $this->type = $session->type->value;
        $this->exam_date = $session->exam_date->toDateString();
        $this->start_time = substr((string) $session->start_time, 0, 5);
        $this->end_time = substr((string) $session->end_time, 0, 5);
        $this->notes = (string) $session->notes;
        $this->resetValidation();
    }

    public function cancelSessionEdit(): void
    {
        $this->reset(['editingSessionId', 'course_id', 'exam_date', 'start_time', 'end_time', 'notes']);
        $this->type = ExamType::REGULAR->value;
        $this->resetValidation();
    }

    public function saveSession(): void
    {
        abort_unless(auth()->user()->can('exam_schedules.edit'), 403);

        $this->validate();

        $attributes = [
            'course_id' => (int) $this->course_id,
            'type' => $this->type,
            'exam_date' => $this->exam_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];

        try {
            $service = app(ExamScheduleService::class);

            if ($this->editingSessionId) {
                $service->updateSession($this->committee->sessions()->findOrFail($this->editingSessionId), $attributes);
                $message = 'تم تحديث ميعاد الامتحان';
            } else {
                $service->createSession($this->committee, $attributes);
                $message = 'تم إضافة ميعاد الامتحان';
            }
        } catch (ExamScheduleException $e) {
            $this->dispatch('toast', ['message' => $e->getMessage(), 'type' => 'danger']);

            return;
        }

        $this->committee->refresh();
        $this->cancelSessionEdit();
        $this->dispatch('toast', ['message' => $message, 'type' => 'success']);
    }

    public function deleteSession($sessionId): void
    {
        abort_unless(auth()->user()->can('exam_schedules.edit'), 403);

        app(ExamScheduleService::class)->deleteSession($this->committee->sessions()->findOrFail($sessionId));
        $this->committee->refresh();

        if ((int) $this->editingSessionId === (int) $sessionId) {
            $this->cancelSessionEdit();
        }

        $this->dispatch('toast', ['message' => 'تم حذف ميعاد الامتحان', 'type' => 'success']);
    }

    public function publish(): void
    {
        abort_unless(auth()->user()->can('exam_schedules.publish'), 403);

        try {
            app(ExamScheduleService::class)->publish($this->committee);
        } catch (ExamScheduleException $e) {
            $this->dispatch('toast', ['message' => $e->getMessage(), 'type' => 'danger']);

            return;
        }

        $this->committee->refresh();
        $this->dispatch('toast', ['message' => 'تم نشر جدول اللجنة بنجاح', 'type' => 'success']);
    }

    public function unpublish(): void
    {
        abort_unless(auth()->user()->can('exam_schedules.publish'), 403);

        app(ExamScheduleService::class)->unpublish($this->committee);
        $this->committee->refresh();
        $this->dispatch('toast', ['message' => 'تم إخفاء جدول اللجنة عن الطلاب', 'type' => 'success']);
    }

    public function render()
    {
        $service = app(ExamScheduleService::class);

        $this->committee->loadMissing(['venue:id,name', 'year:id,year']);

        $chips = $this->committee->members()
            ->with('student:id,name,username')
            ->orderBy('seat_number')
            ->get();

        $search = trim((string) $this->memberSearch);

        $members = $this->committee->members()
            ->with(['student:id,name,username,section_id,level_id', 'student.section:id,name', 'student.level:id,name'])
            ->when($search !== '', fn ($query) => $query->whereHas('student', fn ($student) => $student
                ->where('username', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('seat_number')
            ->paginate(25, ['*'], 'membersPage');

        $courseCounts = $service->courseCounts($this->committee);
        $memberCourseIds = $service->memberCourseIds($this->committee);

        $sessions = $this->committee->sessions()
            ->with('course:id,name,code')
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        $scheduledCourseIds = $sessions->pluck('course_id')->map(fn ($id) => (int) $id)->all();

        return view('livewire.admin.exam-schedule.manage', [
            'chips' => $chips,
            'members' => $members,
            'memberCourseIds' => $memberCourseIds,
            'sessions' => $sessions,
            'courseCounts' => $courseCounts,
            'courses' => Course::query()->whereIn('id', array_keys($courseCounts) ?: [0])->orderBy('name')->get(['id', 'name', 'code']),
            'scheduledCourseIds' => $scheduledCourseIds,
            'examTypes' => ExamType::cases(),
            'window' => $this->committee->year?->semesterExamWindow($this->committee->semester),
        ])->extends('admin.layouts.app')->section('content');
    }
}
