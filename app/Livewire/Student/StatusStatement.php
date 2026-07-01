<?php

namespace App\Livewire\Student;

use App\Models\Registration;
use App\Models\Grade;
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
        $totalPoints = 0;
        $totalHours = 0;
        $earnedHours = 0;

        $gradePoints = Grade::orderBy('order')->pluck('order', 'name')->toArray();

        foreach ($courses as $courseReg) {
            $grade = $courseReg->grade;
            if (!$grade) {
                continue;
            }

            $course = $courseReg->course;
            $hours = $course->hours;
            $points = $gradePoints[$grade->name] ?? 0;

            if ($points > 0) {
                $totalPoints += $hours * $points;
                $totalHours += $hours;
                $earnedHours += $hours;
            }
        }

        $gpa = $totalHours > 0 ? round($totalPoints / $totalHours, 2) : 0;

        return [
            'gpa' => $gpa,
            'total_hours' => $totalHours,
            'earned_hours' => $earnedHours,
        ];
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
