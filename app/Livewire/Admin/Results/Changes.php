<?php

namespace App\Livewire\Admin\Results;

use App\Enums\ResultChangeSource;
use App\Enums\Semester;
use App\Models\ResultChange;
use App\Models\Year;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The results change log: every upload, manual edit and excuse that changed a
 * result, with the values before and after.
 */
class Changes extends Component
{
    use WithPagination;

    public ?int $yearId = null;

    public string $semester = '';

    public string $source = '';

    public string $studentCode = '';

    public string $courseCode = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $changes = ResultChange::query()
            ->with(['user', 'registrationCourse.course', 'registrationCourse.registration.year', 'registrationCourse.registration.student'])
            ->when($this->source !== '', fn (Builder $query) => $query->where('source', $this->source))
            ->whereHas('registrationCourse', fn (Builder $enrollment) => $enrollment
                ->when(trim($this->courseCode) !== '', fn (Builder $query) => $query->whereHas('course', fn (Builder $course) => $course->where('code', 'like', '%'.trim($this->courseCode).'%')))
                ->whereHas('registration', fn (Builder $registration) => $registration
                    ->when($this->yearId, fn (Builder $query) => $query->where('year_id', $this->yearId))
                    ->when($this->semester !== '', fn (Builder $query) => $query->where('semester', $this->semester))
                    ->when(trim($this->studentCode) !== '', fn (Builder $query) => $query->whereHas('student', fn (Builder $student) => $student->where('username', trim($this->studentCode))))))
            ->latest('id')
            ->paginate(50);

        return view('livewire.admin.results.changes', [
            'changes' => $changes,
            'years' => Year::query()->orderByDesc('year')->get(),
            'semesters' => Semester::cases(),
            'sources' => ResultChangeSource::cases(),
        ])->extends('admin.layouts.app')->section('content');
    }
}
