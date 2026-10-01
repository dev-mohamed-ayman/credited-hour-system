<?php

namespace App\Livewire\Admin\LectureSchedule;

use App\Enums\DayOfWeek;
use App\Exceptions\LectureScheduleConflictException;
use App\Models\Course;
use App\Models\LectureSchedule;
use App\Models\Venue;
use App\Services\LectureScheduleService;
use App\Services\StudentSectionDistributionService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?Course $course = null;

    public ?LectureSchedule $schedule = null;

    public $venue_id = '';

    public $day = '';

    public $start_time = '';

    public $end_time = '';

    public $section_numbers = [];

    public $range_from = '';

    public $range_to = '';

    protected function rules(): array
    {
        return [
            'venue_id' => 'required|exists:venues,id',
            'day' => ['required', Rule::enum(DayOfWeek::class)],
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'section_numbers' => 'required|array|min:1',
            'section_numbers.*' => 'integer|min:1',
        ];
    }

    protected function messages(): array
    {
        return [
            'venue_id.required' => 'يجب اختيار المكان',
            'venue_id.exists' => 'المكان المختار غير موجود',
            'day.required' => 'يجب اختيار اليوم',
            'day.enum' => 'اليوم المختار غير مسموح به',
            'start_time.required' => 'وقت البداية مطلوب',
            'start_time.date_format' => 'تنسيق وقت البداية غير صحيح',
            'end_time.required' => 'وقت النهاية مطلوب',
            'end_time.date_format' => 'تنسيق وقت النهاية غير صحيح',
            'end_time.after' => 'يجب أن يكون وقت النهاية أكبر من وقت البداية',
            'section_numbers.required' => 'يجب اختيار سكشن واحد على الأقل',
            'section_numbers.min' => 'يجب اختيار سكشن واحد على الأقل',
            'section_numbers.*.integer' => 'رقم السكشن غير صحيح',
            'section_numbers.*.min' => 'رقم السكشن غير صحيح',
        ];
    }

    public function mount(?Course $course = null, ?LectureSchedule $schedule = null): void
    {
        if ($schedule?->exists) {
            abort_unless(auth()->user()->can('lecture_schedules.edit'), 403);

            $this->schedule = $schedule;
            $this->course = $schedule->course;
            $this->venue_id = $schedule->venue_id;
            $this->day = $schedule->day->value;
            $this->start_time = substr((string) $schedule->start_time, 0, 5);
            $this->end_time = substr((string) $schedule->end_time, 0, 5);
            $this->section_numbers = $schedule->section_numbers ?? [];

            return;
        }

        abort_unless($course?->exists && auth()->user()->can('lecture_schedules.create'), 403);

        $this->course = $course;
    }

    /**
     * "From section X to section Y" shorthand: reversed ranges are normalized
     * and merged with the checkbox picks.
     */
    public function applyRange(): void
    {
        if (! $this->range_from || ! $this->range_to) {
            return;
        }

        $range = range(min((int) $this->range_from, (int) $this->range_to), max((int) $this->range_from, (int) $this->range_to));

        $this->section_numbers = app(LectureScheduleService::class)->normalizeSectionNumbers(array_merge(
            $this->section_numbers,
            array_intersect($range, array_keys($this->availableSections())),
        ));
    }

    public function selectAllSections(): void
    {
        $this->section_numbers = array_keys($this->availableSections());
    }

    public function clearSections(): void
    {
        $this->section_numbers = [];
    }

    public function save(): void
    {
        $permission = $this->schedule ? 'lecture_schedules.edit' : 'lecture_schedules.create';
        abort_unless(auth()->user()->can($permission), 403);

        $this->validate();

        $venue = Venue::findOrFail($this->venue_id);

        if ($this->schedule === null && ! $venue->is_active) {
            $this->addError('venue_id', 'لا يمكن اختيار مكان غير مفعّل لجلسة جديدة');

            return;
        }

        $attributes = [
            'venue_id' => (int) $this->venue_id,
            'day' => DayOfWeek::from($this->day),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'section_numbers' => $this->section_numbers,
        ];

        try {
            $service = app(LectureScheduleService::class);

            if ($this->schedule) {
                $service->update($this->schedule, $attributes);
                $message = 'تم تحديث جلسة المحاضرة بنجاح';
            } else {
                $service->create($this->course, $attributes);
                $message = 'تم إضافة جلسة المحاضرة بنجاح';
            }
        } catch (LectureScheduleConflictException $e) {
            $this->dispatch('toast', ['message' => $e->getMessage(), 'type' => 'danger']);

            return;
        }

        $this->dispatch('toast', ['message' => $message, 'type' => 'success']);
        $this->redirectRoute('lecture-schedules.index', ['course' => $this->course->id], navigate: true);
    }

    /**
     * @return array<int, int> section number => students count
     */
    public function availableSections(): array
    {
        return app(StudentSectionDistributionService::class)
            ->studentsCountPerSection($this->course->department_id, $this->course->level_id);
    }

    public function venueOptions()
    {
        return Venue::query()
            ->when(
                $this->schedule === null,
                fn ($query) => $query->where('is_active', true),
                fn ($query) => $query->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $this->schedule->venue_id)),
            )
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        $sections = $this->availableSections();
        $venue = $this->venue_id ? Venue::find($this->venue_id) : null;

        $selectedTotal = 0;
        if ($this->section_numbers) {
            $selectedTotal = app(LectureScheduleService::class)->selectedStudentsCount($this->course, $this->section_numbers);
        }

        return view('livewire.admin.lecture-schedule.form', [
            'sections' => $sections,
            'venues' => $this->venueOptions(),
            'venue' => $venue,
            'selectedTotal' => $selectedTotal,
        ])->extends('admin.layouts.app')->section('content');
    }
}
