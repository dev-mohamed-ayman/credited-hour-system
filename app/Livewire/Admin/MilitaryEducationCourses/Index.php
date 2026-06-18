<?php

namespace App\Livewire\Admin\MilitaryEducationCourses;

use App\Enums\MilitaryEducationCourseStatus;
use App\Models\MilitaryEducationCourse;
use App\Models\Setting;
use App\Services\MilitaryEducationService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public $search = '';

    #[Url(except: '')]
    public $gender = '';

    #[Url(except: '')]
    public $status = '';

    public $showCreateModal = false;

    public $name;

    public $selectedGender;

    public $capacity;

    public $feeAmount;

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'selectedGender' => 'required|in:male,female',
            'capacity' => 'required|integer|min:1',
            'feeAmount' => 'required|numeric|min:0',
        ];
    }

    protected function messages()
    {
        return [
            'name.required' => 'اسم الدورة مطلوب',
            'selectedGender.required' => 'النوع مطلوب',
            'selectedGender.in' => 'النوع يجب أن يكون ذكر أو أنثى',
            'capacity.required' => 'السعة مطلوبة',
            'capacity.integer' => 'السعة يجب أن تكون رقماً صحيحاً',
            'capacity.min' => 'السعة يجب أن تكون على الأقل 1',
            'feeAmount.required' => 'قيمة المصاريف مطلوبة',
            'feeAmount.numeric' => 'قيمة المصاريف يجب أن تكون رقماً',
            'feeAmount.min' => 'قيمة المصاريف يجب أن تكون على الأقل 0',
        ];
    }

    public function mount(MilitaryEducationService $service)
    {
        $setting = Setting::first();
        $this->feeAmount = $setting?->military_education_default_fee ?? 0;
    }

    public function updated($property)
    {
        if (in_array($property, ['search', 'gender', 'status'])) {
            $this->resetPage();
        }
    }

    public function createCourse(MilitaryEducationService $service)
    {
        $this->validate();

        // Check if there's already an active course for this gender
        $existingActive = MilitaryEducationCourse::where('gender', $this->selectedGender)
            ->where('status', MilitaryEducationCourseStatus::ACTIVE)
            ->exists();

        if ($existingActive) {
            $this->addError('selectedGender', 'يوجد دورة تربية عسكرية مفتوحة بالفعل لهذا النوع');

            return;
        }

        DB::transaction(function () use ($service) {
            $course = MilitaryEducationCourse::create([
                'name' => $this->name,
                'gender' => $this->selectedGender,
                'capacity' => $this->capacity,
                'fee_amount' => $this->feeAmount,
                'status' => MilitaryEducationCourseStatus::ACTIVE,
            ]);

            // Auto-enroll eligible students
            $service->autoEnrollStudents($course);
        });

        $this->dispatch('toast', ['message' => 'تم إنشاء الدورة وتسجيل الطلاب بنجاح', 'type' => 'success']);
        $this->reset(['name', 'selectedGender', 'capacity', 'feeAmount', 'showCreateModal']);

        // Reset fee amount to default
        $setting = Setting::first();
        $this->feeAmount = $setting?->military_education_default_fee ?? 0;
    }

    public function closeCourse($courseId)
    {
        $course = MilitaryEducationCourse::findOrFail($courseId);
        $course->update(['status' => MilitaryEducationCourseStatus::CLOSED]);
        $this->dispatch('toast', ['message' => 'تم إغلاق الدورة بنجاح', 'type' => 'success']);
    }

    public function render()
    {
        $statusEnum = null;
        if ($this->status === 'active') {
            $statusEnum = \App\Enums\MilitaryEducationCourseStatus::ACTIVE;
        } elseif ($this->status === 'closed') {
            $statusEnum = \App\Enums\MilitaryEducationCourseStatus::CLOSED;
        }

        $courses = MilitaryEducationCourse::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->when($this->gender, function ($query) {
                $query->where('gender', $this->gender);
            })
            ->when($statusEnum, function ($query) use ($statusEnum) {
                $query->where('status', $statusEnum);
            })
            ->withCount('enrollments')
            ->latest()
            ->paginate(10);

        return view('livewire.admin.military-education-courses.index', compact('courses'))
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
