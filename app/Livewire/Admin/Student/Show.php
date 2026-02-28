<?php

namespace App\Livewire\Admin\Student;

use App\Models\Student;
use Livewire\Component;

class Show extends Component
{
    public Student $student;

    public function mount(Student $student)
    {
        $this->student = $student->load(['section', 'level', 'country', 'city', 'scores']);
    }

    public function render()
    {
        return view('livewire.admin.student.show');
    }
}
