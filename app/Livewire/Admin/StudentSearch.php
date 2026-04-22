<?php

namespace App\Livewire\Admin;

use App\Models\Student;
use Livewire\Component;

class StudentSearch extends Component
{
    public string $searchCode = '';

    public ?Student $student = null;

    public bool $searched = false;

    public function search(): void
    {
        $this->validate([
            'searchCode' => 'required|string',
        ]);

        $this->student = Student::where('username', trim($this->searchCode))
            ->with(['level', 'section', 'academicAdvisor', 'warnings' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->first();

        $this->searched = true;

        if (! $this->student) {
            $this->dispatch('error', 'لم يتم العثور على طالب بهذا الكود.');
        }
    }

    public function clear(): void
    {
        $this->searchCode = '';
        $this->student = null;
        $this->searched = false;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.admin.student-search.index')
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
