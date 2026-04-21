<?php

namespace App\Livewire\Admin\StudentWarning;

use App\Enums\Student\StudentStatus;
use App\Enums\Student\StudentWarningType;
use App\Models\Student;
use App\Models\StudentWarning;
use Livewire\Component;

class Index extends Component
{
    public $searchCode = '';
    public $student = null;
    public $warnings = [];

    public function search()
    {
        $this->validate([
            'searchCode' => 'required|string',
        ]);

        $this->student = Student::where('username', $this->searchCode)->first();

        if ($this->student) {
            $this->loadWarnings();
        } else {
            $this->warnings = [];
            $this->dispatch('error', 'لم يتم العثور على طالب بهذا الكود.');
        }
    }

    public function loadWarnings()
    {
        if ($this->student) {
            $this->warnings = $this->student->warnings()->orderBy('created_at', 'desc')->get();
        }
    }

    public function cancelWarning($id)
    {
        $warning = StudentWarning::findOrFail($id);
        
        if (!$warning->is_active) {
            return;
        }

        $warning->update(['is_active' => false]);

        // If it's a danger warning, check if we need to reactivate the student
        if ($warning->type === StudentWarningType::DANGER) {
            $student = $warning->student;
            
            $hasOtherDangerWarnings = $student->warnings()
                ->where('type', StudentWarningType::DANGER->value)
                ->where('is_active', true)
                ->exists();

            if (!$hasOtherDangerWarnings) {
                // Assuming REGISTERED is the active status. We could also just let the admin manually reactivate
                // But following the plan to automatically reactivate if no other dangers exist.
                $student->update([
                    'status' => StudentStatus::REGISTERED->value,
                ]);
            }
        }

        $this->loadWarnings();
        $this->dispatch('success', 'تم إلغاء التنبيه بنجاح.');
    }

    public function render()
    {
        return view('livewire.admin.student-warning.index')->extends('admin.layouts.app')->section('content');
    }
}
