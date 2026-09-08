<?php

namespace App\Livewire\Admin;

use App\Enums\AcademicAdvisingStatus;
use App\Enums\Semester;
use App\Enums\SemesterStatus;
use App\Models\Year;
use Livewire\Component;

class YearSettings extends Component
{
    public $years;

    public $selectedYearId;

    public $selectedYear;

    public $firstSemesterStatus;

    public $secondSemesterStatus;

    public $summerSemesterStatus;

    public $academicAdvisingStatus;

    public $first_semester_exam_from = '';

    public $first_semester_exam_to = '';

    public $second_semester_exam_from = '';

    public $second_semester_exam_to = '';

    public $summer_exam_from = '';

    public $summer_exam_to = '';

    public function mount()
    {
        $this->years = Year::latest()->get();

        if ($this->years->isNotEmpty()) {
            $this->selectedYearId = $this->years->first()->id;
            $this->loadYearData();
        }
    }

    public function selectYear($id)
    {
        $this->selectedYearId = $id;
        $this->loadYearData();
    }

    public function loadYearData()
    {
        $this->selectedYear = Year::find($this->selectedYearId);

        if ($this->selectedYear) {
            $this->firstSemesterStatus = $this->selectedYear->first_semester_status->value;
            $this->secondSemesterStatus = $this->selectedYear->second_semester_status->value;
            $this->summerSemesterStatus = $this->selectedYear->summer_semester_status->value;
            $this->academicAdvisingStatus = $this->selectedYear->academic_advising_status->value;

            foreach ([
                'first_semester_exam_from',
                'first_semester_exam_to',
                'second_semester_exam_from',
                'second_semester_exam_to',
                'summer_exam_from',
                'summer_exam_to',
            ] as $field) {
                $this->$field = $this->selectedYear->$field?->toDateString() ?? '';
            }
        }
    }

    public function updateSemester($semester, $status)
    {
        abort_unless(auth()->user()->can('years.edit'), 403);

        $this->selectedYear->setSemesterStatus(Semester::from($semester), SemesterStatus::from($status));

        $this->loadYearData();

        session()->flash('message', 'تم تحديث حالة الترم بنجاح');
    }

    public function updateAcademicAdvising($status)
    {
        abort_unless(auth()->user()->can('years.edit'), 403);

        $this->selectedYear->update([
            'academic_advising_status' => AcademicAdvisingStatus::from($status),
        ]);

        $this->loadYearData();

        session()->flash('message', 'تم تحديث حالة الإرشاد الأكاديمي بنجاح');
    }

    public function updateExamWindows()
    {
        abort_unless(auth()->user()->can('years.edit'), 403);

        $validated = $this->validate(
            [
                'first_semester_exam_from' => 'nullable|date',
                'first_semester_exam_to' => 'nullable|date|after_or_equal:first_semester_exam_from',
                'second_semester_exam_from' => 'nullable|date',
                'second_semester_exam_to' => 'nullable|date|after_or_equal:second_semester_exam_from',
                'summer_exam_from' => 'nullable|date',
                'summer_exam_to' => 'nullable|date|after_or_equal:summer_exam_from',
            ],
            [
                'first_semester_exam_to.after_or_equal' => 'نهاية فترة امتحانات الترم الأول يجب أن تكون بعد بدايتها',
                'second_semester_exam_to.after_or_equal' => 'نهاية فترة امتحانات الترم الثاني يجب أن تكون بعد بدايتها',
                'summer_exam_to.after_or_equal' => 'نهاية فترة الامتحانات الصيفية يجب أن تكون بعد بدايتها',
            ],
        );

        $this->selectedYear->update($validated);

        $this->loadYearData();

        $this->dispatch('toast', ['message' => 'تم تحديث فترات الامتحانات بنجاح', 'type' => 'success']);
    }

    public function render()
    {
        return view('livewire.admin.year-settings')->extends('admin.layouts.app')->section('content');
    }
}
