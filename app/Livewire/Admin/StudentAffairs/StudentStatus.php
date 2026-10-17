<?php

namespace App\Livewire\Admin\StudentAffairs;

use App\Models\Student;
use App\Services\GpaCalculator;
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

        $this->student = Student::where('username', 'like', '%'.$this->searchQuery.'%')
            ->orWhere('name', 'like', '%'.$this->searchQuery.'%')
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

        if (! $this->student) {
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

        if (! $this->student) {
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
        return app(GpaCalculator::class)->forCourses($courses);
    }

    public function render()
    {
        $recentStudents = collect();
        if (empty($this->student) && strlen($this->searchQuery) >= 2) {
            $recentStudents = Student::where('username', 'like', '%'.$this->searchQuery.'%')
                ->orWhere('name', 'like', '%'.$this->searchQuery.'%')
                ->limit(10)
                ->get();
        }

        return view('livewire.admin.student-affairs.student-status', [
            'recentStudents' => $recentStudents,
        ])->extends('admin.layouts.app')->section('content');
    }
}
