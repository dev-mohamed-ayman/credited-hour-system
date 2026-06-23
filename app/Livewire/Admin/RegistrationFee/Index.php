<?php

namespace App\Livewire\Admin\RegistrationFee;

use App\Models\Department;
use App\Models\Level;
use App\Models\RegistrationFee;
use Livewire\Component;

class Index extends Component
{
    public $departments;

    public $levels;

    public $activeDepartmentId;

    public $activeLevelId;

    public $hour_payment = 0;

    public $ministerial_payment = 0;

    public $hour_payment_remaining = 0;

    public $ministerial_payment_remaining = 0;

    public $total_student_payment = 0;

    public $student_registration_hour = 0;

    public $number_of_students_per_section = 0;

    public function mount()
    {
        $this->departments = Department::all();
        $this->levels = Level::all();

        if ($this->departments->isNotEmpty()) {
            $this->activeDepartmentId = $this->departments->first()->id;
        }

        if ($this->levels->isNotEmpty()) {
            $this->activeLevelId = $this->levels->first()->id;
        }

        $this->loadFeeData();
    }

    public function setDepartment($id)
    {
        $this->activeDepartmentId = $id;
        $this->loadFeeData();
    }

    public function setLevel($id)
    {
        $this->activeLevelId = $id;
        $this->loadFeeData();
    }

    public function loadFeeData()
    {
        if (! $this->activeDepartmentId || ! $this->activeLevelId) {
            return;
        }

        $fee = RegistrationFee::firstOrCreate(
            ['department_id' => $this->activeDepartmentId, 'level_id' => $this->activeLevelId]
        );

        $this->hour_payment = $fee->hour_payment;
        $this->ministerial_payment = $fee->ministerial_payment;
        $this->hour_payment_remaining = $fee->hour_payment_remaining;
        $this->ministerial_payment_remaining = $fee->ministerial_payment_remaining;
        $this->total_student_payment = $fee->total_student_payment;
        $this->student_registration_hour = $fee->student_registration_hour;
        $this->number_of_students_per_section = $fee->number_of_students_per_section;
    }

    public function save()
    {
        abort_unless(auth()->user()->can('registration_fees.edit'), 403);

        $this->validate([
            'hour_payment' => 'required|numeric|min:0',
            'ministerial_payment' => 'required|numeric|min:0',
            'hour_payment_remaining' => 'required|numeric|min:0',
            'ministerial_payment_remaining' => 'required|numeric|min:0',
            'total_student_payment' => 'required|numeric|min:0',
            'student_registration_hour' => 'required|numeric|min:0',
            'number_of_students_per_section' => 'required|integer|min:0',
        ]);

        RegistrationFee::updateOrCreate(
            ['department_id' => $this->activeDepartmentId, 'level_id' => $this->activeLevelId],
            [
                'hour_payment' => $this->hour_payment,
                'ministerial_payment' => $this->ministerial_payment,
                'hour_payment_remaining' => $this->hour_payment_remaining,
                'ministerial_payment_remaining' => $this->ministerial_payment_remaining,
                'total_student_payment' => $this->total_student_payment,
                'student_registration_hour' => $this->student_registration_hour,
                'number_of_students_per_section' => $this->number_of_students_per_section,
            ]
        );

        session()->flash('message', 'تم تحديث البيانات بنجاح.');
    }

    public function render()
    {
        return view('livewire.admin.registration-fee.index')->extends('admin.layouts.app')->section('content');
    }
}
