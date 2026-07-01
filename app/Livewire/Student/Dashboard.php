<?php

namespace App\Livewire\Student;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        $student = auth('student')->user();
        $totalRegistrations = $student->registrations()->count();

        return view('livewire.student.dashboard', [
            'student' => $student,
            'totalRegistrations' => $totalRegistrations,
        ])
            ->extends('student.layouts.app')
            ->section('content');
    }
}
