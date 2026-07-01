<?php

namespace App\Livewire\Student\RegistrationRecord;

use App\Enums\Semester;
use App\Models\Registration;
use App\Models\Year;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public ?int $searchYear = null;

    public ?Semester $searchSemester = null;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public function updatingSearchYear(): void
    {
        $this->resetPage();
    }

    public function updatingSearchSemester(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['searchYear', 'searchSemester']);
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function render(): View
    {
        $student = auth('student')->user();

        $registrations = Registration::query()
            ->where('student_id', $student->id)
            ->with(['student.level', 'student.section.department', 'year', 'courses'])
            ->when($this->searchYear, fn ($query) => $query->where('year_id', $this->searchYear))
            ->when($this->searchSemester, fn ($query) => $query->where('semester', $this->searchSemester))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);

        $years = Year::query()->latest()->get();

        return view('livewire.student.registration-record.index', [
            'registrations' => $registrations,
            'years' => $years,
        ])
            ->extends('student.layouts.app')
            ->section('content');
    }
}
