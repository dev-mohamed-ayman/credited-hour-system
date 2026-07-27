<?php

namespace App\Livewire\Admin\TransferEquivalency;

use App\Enums\Student\ApplicationCategory;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Models\TransferEquivalency;
use Livewire\Component;

class Index extends Component
{
    public $searchQuery = '';

    public ?Student $student = null;

    public $selectedCourseId = '';

    public $selectedGradeId = '';

    public $equivalencies = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('transfer_equivalency.view'), 403);
    }

    public function searchStudent(): void
    {
        $this->validate([
            'searchQuery' => 'required|string',
        ]);

        $student = Student::where('username', $this->searchQuery)
            ->with(['level', 'section.department'])
            ->first();

        if (! $student) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'لم يتم العثور على طالب بهذا الكود']);

            return;
        }

        if ($student->application_category !== ApplicationCategory::TRANSFERRED) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'هذا الطالب ليس محولاً. معادلة المحولين متاحة فقط للطلاب المحولين.']);
            $this->student = null;

            return;
        }

        $this->student = $student;
        $this->selectedCourseId = '';
        $this->selectedGradeId = '';
        $this->loadEquivalencies();
    }

    public function loadEquivalencies(): void
    {
        if (! $this->student) {
            $this->equivalencies = [];

            return;
        }

        $this->equivalencies = TransferEquivalency::where('student_id', $this->student->id)
            ->with(['course', 'grade'])
            ->get();
    }

    public function addEquivalency(): void
    {
        abort_unless(auth()->user()->can('transfer_equivalency.create'), 403);

        $this->validate([
            'selectedCourseId' => 'required|exists:courses,id',
            'selectedGradeId' => 'required|exists:grades,id',
        ], [
            'selectedCourseId.required' => 'يجب اختيار المادة',
            'selectedGradeId.required' => 'يجب اختيار التقييم',
        ]);

        $alreadyExists = TransferEquivalency::where('student_id', $this->student->id)
            ->where('course_id', $this->selectedCourseId)
            ->exists();

        if ($alreadyExists) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'هذه المادة مضافة بالفعل في المعادلة']);

            return;
        }

        TransferEquivalency::create([
            'student_id' => $this->student->id,
            'course_id' => $this->selectedCourseId,
            'grade_id' => $this->selectedGradeId,
        ]);

        $this->selectedCourseId = '';
        $this->selectedGradeId = '';
        $this->loadEquivalencies();

        $this->dispatch('alert', ['type' => 'success', 'message' => 'تمت إضافة المادة في المعادلة بنجاح']);
    }

    public function deleteEquivalency(int $id): void
    {
        abort_unless(auth()->user()->can('transfer_equivalency.delete'), 403);

        $equivalency = TransferEquivalency::where('student_id', $this->student->id)
            ->findOrFail($id);

        $equivalency->delete();
        $this->loadEquivalencies();

        $this->dispatch('alert', ['type' => 'success', 'message' => 'تم حذف المادة من المعادلة بنجاح']);
    }

    public function render()
    {
        $courses = collect();
        $grades = collect();

        if ($this->student) {
            $existingCourseIds = TransferEquivalency::where('student_id', $this->student->id)
                ->pluck('course_id');

            $courses = Course::whereNotIn('id', $existingCourseIds)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'hours']);

            $grades = Grade::orderBy('order')->get(['id', 'name']);
        }

        return view('livewire.admin.transfer-equivalency.index', [
            'courses' => $courses,
            'grades' => $grades,
        ])->extends('admin.layouts.app')->section('content');
    }
}
