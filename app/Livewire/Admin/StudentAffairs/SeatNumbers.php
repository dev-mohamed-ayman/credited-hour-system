<?php

namespace App\Livewire\Admin\StudentAffairs;

use App\Enums\Student\StudyStatus;
use App\Models\Department;
use App\Models\Level;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
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

    public function getLastSeatNumberProperty()
    {
        // Try to get max as an integer since it's stored as a string
        $lastStudent = Student::whereNotNull('seat_number')
            ->where('seat_number', '!=', '')
            ->orderByRaw('CAST(seat_number AS UNSIGNED) DESC')
            ->first();

        return $lastStudent ? $lastStudent->seat_number : null;
    }

    public function generate()
    {
        $this->validate();

        $query = Student::where('section_id', $this->section_id)
            ->where('level_id', $this->level_id);

        if (!empty($this->study_status)) {
            $query->where('study_status', $this->study_status);
        }

        $students = $query->orderBy('name', 'asc')->get();

        if ($students->isEmpty()) {
            $this->dispatch('toast', message: 'لا يوجد طلاب مطابقين لهذه الشروط.', type: 'warning');
            return;
        }

        $currentSeatNumber = (int) $this->start_seat_number;

        DB::transaction(function () use ($students, &$currentSeatNumber) {
            foreach ($students as $student) {
                $student->update([
                    'seat_number' => (string) $currentSeatNumber
                ]);
                $currentSeatNumber++;
            }
        });

        $this->dispatch('toast', message: 'تم إنشاء أرقام الجلوس لعدد ' . $students->count() . ' طالب بنجاح.', type: 'success');
        $this->start_seat_number = ''; // Reset
    }

    public function render()
    {
        return view('livewire.admin.student-affairs.seat-numbers')
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
