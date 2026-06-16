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
        }
    }

    public function updateSemester($semester, $status)
    {
        $this->selectedYear->setSemesterStatus(Semester::from($semester), SemesterStatus::from($status));

        $this->loadYearData();

        session()->flash('message', 'تم تحديث حالة الترم بنجاح');
    }

    public function updateAcademicAdvising($status)
    {
        $this->selectedYear->update([
            'academic_advising_status' => AcademicAdvisingStatus::from($status),
        ]);

        $this->loadYearData();

        session()->flash('message', 'تم تحديث حالة الإرشاد الأكاديمي بنجاح');
    }

    public function render()
    {
        return view('livewire.admin.year-settings')->extends('admin.layouts.app')->section('content');
    }
}
