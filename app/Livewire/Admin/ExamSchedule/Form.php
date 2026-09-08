<?php

namespace App\Livewire\Admin\ExamSchedule;

use App\Enums\ExamType;
use App\Exceptions\ExamScheduleException;
use App\Models\Course;
use App\Models\ExamSession;
use App\Models\Venue;
use App\Models\Year;
use App\Services\ExamScheduleService;
use App\Support\CourseSemesterMapper;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?Course $course = null;

    public ?ExamSession $session = null;

    public $year_id = '';

    public $type = 'regular';

    public $exam_date = '';

    public $start_time = '';

    public $end_time = '';

    public $notes = '';

    public $committees = [];

    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ExamType::class)],
            'exam_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:2000',
            'committees' => 'array',
            'committees.*.venue_id' => 'required|exists:venues,id',
            'committees.*.name' => 'required|string|max:255',
            'committees.*.capacity' => 'required|integer|min:1',
        ];
    }

    protected function messages(): array
    {
        return [
            'type.required' => 'يجب اختيار نوع الامتحان',
            'type.enum' => 'نوع الامتحان المختار غير مسموح به',
            'exam_date.required' => 'تاريخ الامتحان مطلوب',
            'exam_date.date' => 'تاريخ الامتحان غير صحيح',
            'start_time.required' => 'وقت البداية مطلوب',
            'start_time.date_format' => 'تنسيق وقت البداية غير صحيح',
            'end_time.required' => 'وقت النهاية مطلوب',
            'end_time.date_format' => 'تنسيق وقت النهاية غير صحيح',
            'end_time.after' => 'يجب أن يكون وقت النهاية أكبر من وقت البداية',
            'committees.*.venue_id.required' => 'يجب اختيار مكان لكل لجنة',
            'committees.*.venue_id.exists' => 'المكان المختار غير موجود',
            'committees.*.name.required' => 'اسم اللجنة مطلوب',
            'committees.*.capacity.required' => 'سعة اللجنة مطلوبة',
            'committees.*.capacity.integer' => 'سعة اللجنة يجب أن تكون رقمًا',
            'committees.*.capacity.min' => 'سعة اللجنة يجب أن تكون 1 على الأقل',
        ];
    }

    public function mount(?Course $course = null, ?ExamSession $session = null): void
    {
        if ($session?->exists) {
            abort_unless(auth()->user()->can('exam_schedules.edit'), 403);

            $this->session = $session;
            $this->course = $session->course;
            $this->year_id = $session->year_id;
            $this->type = $session->type->value;
            $this->exam_date = $session->exam_date->toDateString();
            $this->start_time = substr((string) $session->start_time, 0, 5);
            $this->end_time = substr((string) $session->end_time, 0, 5);
            $this->notes = (string) $session->notes;
            $this->committees = $session->committees()->orderBy('id')->get()
                ->map(fn ($committee) => [
                    'id' => $committee->id,
                    'venue_id' => $committee->venue_id,
                    'name' => $committee->name,
                    'capacity' => $committee->capacity,
                ])
                ->all();

            return;
        }

        abort_unless($course?->exists && auth()->user()->can('exam_schedules.create'), 403);

        $this->course = $course;
        $this->year_id = request()->integer('year') ?: (Year::current()?->id ?? '');
    }

    public function addCommittee(): void
    {
        $this->committees[] = ['venue_id' => '', 'name' => '', 'capacity' => ''];
    }

    public function removeCommittee($index): void
    {
        unset($this->committees[$index]);
        $this->committees = array_values($this->committees);
    }

    public function save(): void
    {
        $permission = $this->session ? 'exam_schedules.edit' : 'exam_schedules.create';
        abort_unless(auth()->user()->can($permission), 403);

        $this->committees = $this->committeePayload();
        $this->validate();

        $semester = CourseSemesterMapper::toEnum((string) $this->course->semester);

        if ($semester === null || $this->year_id === '') {
            $this->dispatch('toast', [
                'message' => 'تعذر تحديد الترم أو السنة الدراسية للمادة',
                'type' => 'danger',
            ]);

            return;
        }

        $attributes = [
            'course_id' => $this->course->id,
            'year_id' => (int) $this->year_id,
            'semester' => $semester,
            'type' => ExamType::from($this->type),
            'exam_date' => $this->exam_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];

        try {
            $service = app(ExamScheduleService::class);

            if ($this->session) {
                $service->update($this->session, $attributes, $this->committees);
                $message = 'تم تحديث جلسة الامتحان بنجاح';
            } else {
                $service->create($attributes, $this->committees);
                $message = 'تم إضافة جلسة الامتحان بنجاح';
            }
        } catch (ExamScheduleException $e) {
            $this->dispatch('toast', ['message' => $e->getMessage(), 'type' => 'danger']);

            return;
        }

        $this->dispatch('toast', ['message' => $message, 'type' => 'success']);
        $this->redirectRoute('exam-schedules.index', ['year' => $this->year_id, 'semester' => $semester->value], navigate: true);
    }

    /**
     * Drop fully-empty repeater rows; keep the rest as clean payloads.
     *
     * @return array<int, array<string, mixed>>
     */
    private function committeePayload(): array
    {
        return array_values(array_filter(
            array_map(fn ($committee) => [
                'id' => $committee['id'] ?? null,
                'venue_id' => $committee['venue_id'] ?? '',
                'name' => $committee['name'] ?? '',
                'capacity' => $committee['capacity'] ?? '',
            ], $this->committees),
            fn ($committee) => $committee['venue_id'] !== '' || $committee['name'] !== '' || $committee['capacity'] !== '',
        ));
    }

    public function render()
    {
        $semester = CourseSemesterMapper::toEnum((string) $this->course->semester);

        $examineeCount = $this->session
            ? app(ExamScheduleService::class)->examineeCount($this->session)
            : ($semester !== null && $this->year_id !== ''
                ? app(ExamScheduleService::class)->courseAudienceCount($this->course->id, (int) $this->year_id, $semester)
                : 0);

        $capacityTotal = array_sum(array_map(
            fn ($committee) => (int) ($committee['capacity'] ?? 0),
            $this->committees,
        ));

        return view('livewire.admin.exam-schedule.form', [
            'examTypes' => ExamType::cases(),
            'venues' => Venue::where('is_active', true)->orderBy('name')->get(),
            'examineeCount' => $examineeCount,
            'capacityTotal' => $capacityTotal,
        ])->extends('admin.layouts.app')->section('content');
    }
}
