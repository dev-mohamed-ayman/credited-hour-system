<?php

namespace App\Livewire\Admin\Student;

use App\Models\Student;
use Livewire\Component;

class Show extends Component
{
    public Student $student;

    public function mount(Student $student)
    {
        $this->student = $student->load(['section', 'level', 'country', 'city', 'scores', 'year', 'militaryEducationEnrollments.course', 'militaryEducationEnrollments.year']);
    }

    public function render()
    {
        return view('livewire.admin.student.show', [
            'termRecords' => $this->student->termRecords()
                ->with(['closure.year', 'levelBefore', 'levelAfter'])
                ->latest('id')
                ->get(),
            'academicWarningCount' => $this->student->warnings()->academic()->where('is_active', true)->count(),
        ]);
    }
}
