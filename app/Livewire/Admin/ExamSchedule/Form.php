<?php

namespace App\Livewire\Admin\ExamSchedule;

use App\Enums\Semester;
use App\Exceptions\ExamScheduleException;
use App\Models\ExamCommittee;
use App\Models\Venue;
use App\Models\Year;
use App\Services\ExamScheduleService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    public ?ExamCommittee $committee = null;

    #[Locked]
    public $year_id = '';

    #[Locked]
    public $semester = '';

    public $venue_id = '';

    public $name = '';

    public $capacity = '';

    public $notes = '';

    protected function rules(): array
    {
        return [
            'year_id' => 'required|exists:years,id',
            'semester' => ['required', Rule::enum(Semester::class)],
            'venue_id' => 'required|exists:venues,id',
            'name' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    protected function messages(): array
    {
        return [
            'year_id.required' => 'يجب اختيار السنة الدراسية',
            'year_id.exists' => 'السنة الدراسية غير موجودة',
            'semester.required' => 'يجب اختيار الترم',
            'semester.enum' => 'الترم المختار غير صحيح',
            'venue_id.required' => 'يجب اختيار مكان اللجنة',
            'venue_id.exists' => 'المكان المختار غير موجود',
            'name.required' => 'اسم اللجنة مطلوب',
            'name.max' => 'اسم اللجنة طويل جدًا',
            'capacity.required' => 'سعة اللجنة مطلوبة',
            'capacity.integer' => 'سعة اللجنة يجب أن تكون رقمًا',
            'capacity.min' => 'سعة اللجنة يجب أن تكون 1 على الأقل',
        ];
    }

    public function mount(?ExamCommittee $committee = null): void
    {
        if ($committee?->exists) {
            abort_unless(auth()->user()->can('exam_schedules.edit'), 403);

            $this->committee = $committee;
            $this->year_id = $committee->year_id;
            $this->semester = $committee->semester->value;
            $this->venue_id = $committee->venue_id;
            $this->name = $committee->name;
            $this->capacity = $committee->capacity;
            $this->notes = (string) $committee->notes;

            return;
        }

        abort_unless(auth()->user()->can('exam_schedules.create'), 403);

        $this->committee = null;
        $this->year_id = Year::current()?->id ?? '';
        $this->semester = Year::currentSemester()?->value ?? '';
    }

    public function updatedVenueId($value): void
    {
        if ($this->capacity === '' || $this->capacity === null) {
            $this->capacity = Venue::find($value)?->capacity ?? '';
        }
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can($this->committee ? 'exam_schedules.edit' : 'exam_schedules.create'), 403);

        $this->validate();

        if (! $this->committee && (Year::current()?->id !== (int) $this->year_id || Year::currentSemester()?->value !== $this->semester)) {
            $this->dispatch('toast', ['message' => 'لا يمكن إضافة لجنة إلا للسنة والترم الحاليين', 'type' => 'danger']);

            return;
        }

        $attributes = [
            'year_id' => (int) $this->year_id,
            'semester' => $this->semester,
            'venue_id' => (int) $this->venue_id,
            'name' => $this->name,
            'capacity' => (int) $this->capacity,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];

        try {
            $service = app(ExamScheduleService::class);

            if ($this->committee) {
                $service->updateCommittee($this->committee, $attributes);
                $committee = $this->committee;
                $message = 'تم تحديث اللجنة بنجاح';
            } else {
                $committee = $service->createCommittee($attributes);
                $message = 'تم إضافة اللجنة بنجاح — أضف الطلاب والمواعيد';
            }
        } catch (ExamScheduleException $e) {
            $this->dispatch('toast', ['message' => $e->getMessage(), 'type' => 'danger']);

            return;
        }

        session()->flash('success', $message);
        $this->redirectRoute('exam-schedules.manage', $committee, navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.exam-schedule.form', [
            'termYear' => Year::find($this->year_id),
            'venues' => Venue::query()
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $this->committee?->venue_id ?? 0))
                ->orderBy('name')
                ->get(),
        ])->extends('admin.layouts.app')->section('content');
    }
}
