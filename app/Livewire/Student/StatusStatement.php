<?php

namespace App\Livewire\Student;

use App\Services\GpaCalculator;
use Livewire\Component;

class StatusStatement extends Component
{
    public ?int $registrationId = null;

    public function mount(): void
    {
        $this->registrationId = request()->query('registration');
    }

    public function calculateGPA($courses): array
    {
        return app(GpaCalculator::class)->forCourses($courses);
    }

    public function render()
    {
        $student = auth('student')->user();
        $student->load([
            'level',
            'section',
            'section.department',
            'year',
            'registrations' => function ($q) {
                $q->orderByDesc('year_id')
                    ->orderByDesc('semester')
                    ->with([
                        'year',
                        'courses' => function ($qc) {
                            $qc->with(['course', 'grade']);
                        },
                    ]);
            },
        ]);

        $selectedRegistration = null;
        if ($this->registrationId) {
            $selectedRegistration = $student->registrations->where('id', $this->registrationId)->first();
        }

        return view('livewire.student.status-statement', [
            'student' => $student,
            'selectedRegistration' => $selectedRegistration,
        ])
            ->extends('student.layouts.app')
            ->section('content');
    }
}
