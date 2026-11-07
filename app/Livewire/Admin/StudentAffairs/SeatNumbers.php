<?php

namespace App\Livewire\Admin\StudentAffairs;

use App\Exceptions\ExamScheduleException;
use App\Models\Department;
use App\Models\ExamCommitteeStudent;
use App\Models\Level;
use App\Models\Section;
use App\Models\Student;
use App\Models\Year;
use App\Services\ExamScheduleService;
use Livewire\Component;

class SeatNumbers extends Component
{
    public $department_id = '';

    public $section_id = '';

    public $level_id = '';

    public $study_status = '';

    public $start_seat_number = '';

    public $departments = [];

    public $sections = [];

    public $levels = [];

    protected $rules = [
        'department_id' => 'required',
        'section_id' => 'required',
        'level_id' => 'required',
        'study_status' => 'nullable',
        'start_seat_number' => 'required|numeric|min:1',
    ];

    public function mount()
    {
        $this->departments = Department::all();
        $this->levels = Level::all();
    }

    public function updatedDepartmentId($value)
    {
        $this->sections = Section::where('department_id', $value)->get();
        $this->section_id = '';
    }

    public function getCurrentYearProperty(): ?Year
    {
        return Year::current();
    }

    public function getLastSeatNumberProperty(): ?int
    {
        $year = $this->currentYear;

        return ExamCommitteeStudent::query()
            ->forTerm($year, $year?->getCurrentSemester())
            ->max('seat_number');
    }

    public function generate(ExamScheduleService $examScheduleService)
    {
        $this->validate();

        $year = $this->currentYear;
        $semester = $year?->getCurrentSemester();

        if ($semester === null) {
            $this->dispatch('toast', message: 'لا يوجد ترم حالي مفتوح.', type: 'error');

            return;
        }

        $query = Student::where('section_id', $this->section_id)
            ->where('level_id', $this->level_id);

        if (! empty($this->study_status)) {
            $query->where('study_status', $this->study_status);
        }

        $students = $query->orderBy('name', 'asc')->get();

        if ($students->isEmpty()) {
            $this->dispatch('toast', message: 'لا يوجد طلاب مطابقين لهذه الشروط.', type: 'warning');

            return;
        }

        try {
            $result = $examScheduleService->numberSeats($year, $semester, $students, (int) $this->start_seat_number);
        } catch (ExamScheduleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        if ($result['numbered'] === 0) {
            $this->dispatch('toast', message: 'لا يوجد طلاب من هؤلاء في أي لجنة امتحانات للترم الحالي — أضفهم للجان أولاً.', type: 'warning');

            return;
        }

        $message = 'تم إنشاء أرقام الجلوس لعدد '.$result['numbered'].' طالب بنجاح.';

        if ($result['skipped'] > 0) {
            $message .= ' ('.$result['skipped'].' طالب ليسوا في أي لجنة للترم الحالي)';
        }

        $this->dispatch('toast', message: $message, type: 'success');
        $this->start_seat_number = '';
    }

    public function render()
    {
        return view('livewire.admin.student-affairs.seat-numbers')
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
