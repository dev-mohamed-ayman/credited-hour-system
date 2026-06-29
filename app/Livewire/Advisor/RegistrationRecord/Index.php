<?php

namespace App\Livewire\Advisor\RegistrationRecord;

use App\Enums\Semester;
use App\Models\Registration;
use App\Models\Year;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $searchStudent = '';

    public ?int $searchYear = null;

    public ?Semester $searchSemester = null;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public function updatingSearchStudent(): void
    {
        $this->resetPage();
    }

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
        $this->reset(['searchStudent', 'searchYear', 'searchSemester']);
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
        $registrations = Registration::query()
            ->whereHas('student', function ($query) {
                $query->where('academic_advisor_id', auth('advisor')->id());
            })
            ->with(['student.level', 'student.section.department', 'year', 'courses'])
            ->when($this->searchStudent, function ($query) {
                $query->whereHas('student', function ($q) {
                    $q->where('name', 'like', '%'.$this->searchStudent.'%')
                        ->orWhere('username', 'like', '%'.$this->searchStudent.'%');
                });
            })
            ->when($this->searchYear, fn ($query) => $query->where('year_id', $this->searchYear))
            ->when($this->searchSemester, fn ($query) => $query->where('semester', $this->searchSemester))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);

        $years = Year::query()->latest()->get();

        return view('livewire.advisor.registration-record.index', [
            'registrations' => $registrations,
            'years' => $years,
        ])
            ->extends('advisor.layouts.app')
            ->section('content');
    }
}
