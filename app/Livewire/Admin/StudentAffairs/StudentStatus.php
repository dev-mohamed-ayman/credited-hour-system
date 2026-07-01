<?php

namespace App\Livewire\Admin\StudentAffairs;

use App\Models\Student;
use App\Models\Registration;
use App\Models\Grade;
use Livewire\Component;

class StudentStatus extends Component
{
    public $searchQuery;
    public $selectedStudentId;
    public $student;
    public $showScores = false;

    public function searchStudent()
    {
        $this->validate([
            'searchQuery' => 'required|string',
        ]);

        $this->student = Student::where('username', 'like', '%' . $this->searchQuery . '%')
            ->orWhere('name', 'like', '%' . $this->searchQuery . '%')
            ->with([
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
            ])
            ->first();

        if (!$this->student) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'لم يتم العثور على طالب بهذا الاسم أو الكود',
            ]);
            return;
        }

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'تم العثور على الطالب بنجاح',
        ]);
    }

    public function selectStudent($studentId)
    {
        $this->student = Student::with([
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
        ])->find($studentId);

        if (!$this->student) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'لم يتم العثور على الطالب',
            ]);
            return;
        }
    }

    public function clearSearch()
    {
        $this->searchQuery = '';
        $this->student = null;
        $this->showScores = false;
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
        $recentStudents = collect();
        if (empty($this->student) && strlen($this->searchQuery) >= 2) {
            $recentStudents = Student::where('username', 'like', '%' . $this->searchQuery . '%')
                ->orWhere('name', 'like', '%' . $this->searchQuery . '%')
                ->limit(10)
                ->get();
        }

        return view('livewire.admin.student-affairs.student-status', [
            'recentStudents' => $recentStudents,
        ])->extends('admin.layouts.app')->section('content');
    }
}

