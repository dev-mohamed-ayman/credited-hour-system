<?php

namespace App\Livewire\Admin\Results;

use App\Enums\Semester;
use App\Models\Department;
use App\Models\Level;
use App\Models\Year;
use App\Services\ResultReportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Control reports: courses still waiting for results, the GPA ranking (top
 * students), and the grade distribution of each course in a term.
 */
class Reports extends Component
{
    #[Url]
    public string $report = 'pending';

    public ?int $yearId = null;

    public ?string $semester = null;

    public ?int $departmentId = null;

    public ?int $levelId = null;

    public int $limit = 10;

    public function mount(): void
    {
        $this->yearId = Year::current()?->id;
        $this->semester = Year::currentSemester()?->value;
    }

    public function render(): View
    {
        $reports = app(ResultReportService::class);
        $year = $this->yearId ? Year::find($this->yearId) : null;
        $semester = $this->semester ? Semester::tryFrom($this->semester) : null;

        return view('livewire.admin.results.reports', [
            'years' => Year::query()->orderByDesc('year')->get(),
            'semesters' => Semester::cases(),
            'departments' => Department::query()->orderBy('name')->get(),
            'levels' => Level::query()->orderBy('id')->get(),
            'pending' => $this->report === 'pending' && $year && $semester ? $reports->pendingCourses($year, $semester) : collect(),
            'ranking' => $this->report === 'ranking' ? $reports->ranking($this->departmentId, $this->levelId, max(0, $this->limit)) : collect(),
            'distribution' => $this->report === 'distribution' && $year && $semester
                ? $reports->gradeDistribution($year, $semester, $this->departmentId)
                : ['grades' => collect(), 'courses' => collect()],
        ])->extends('admin.layouts.app')->section('content');
    }
}
