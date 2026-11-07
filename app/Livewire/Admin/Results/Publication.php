<?php

namespace App\Livewire\Admin\Results;

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\ResultPublication;
use App\Models\Year;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Announces a term's results to the students (إعلان النتيجة). Until then the
 * student portal shows the term's courses without grades or GPA.
 */
class Publication extends Component
{
    public function publish(int $yearId, string $semester): void
    {
        abort_unless(auth()->user()->can('results.publish'), 403);

        $term = Semester::from($semester);
        Year::findOrFail($yearId);

        ResultPublication::firstOrCreate(
            ['year_id' => $yearId, 'semester' => $term->value],
            ['published_by' => auth()->id()],
        );

        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم إعلان نتيجة '.$term->label().' للطلاب.']);
    }

    public function unpublish(int $yearId, string $semester): void
    {
        abort_unless(auth()->user()->can('results.publish'), 403);

        $term = Semester::from($semester);

        ResultPublication::query()->where('year_id', $yearId)->where('semester', $term->value)->delete();

        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم إخفاء نتيجة '.$term->label().' عن الطلاب.']);
    }

    public function render(): View
    {
        $publications = ResultPublication::query()->with('publishedBy')->get()
            ->keyBy(fn (ResultPublication $publication) => $publication->year_id.'|'.$publication->semester->value);

        $pending = RegistrationCourse::query()
            ->where(fn (Builder $query) => $query->whereNull('grade_id')->orWhereHas('grade', fn (Builder $grade) => $grade->where('is_pending_default', true)))
            ->whereHas('registration', fn (Builder $registration) => $registration->where('status', RegistrationStatus::APPROVED->value))
            ->with('registration:id,year_id,semester')
            ->get(['id', 'registration_id'])
            ->countBy(fn (RegistrationCourse $enrollment) => $enrollment->registration->year_id.'|'.$enrollment->registration->semester->value);

        $terms = Registration::query()
            ->select('year_id', 'semester')
            ->distinct()
            ->with('year')
            ->get()
            ->filter(fn (Registration $registration) => $registration->year !== null)
            ->sortByDesc(fn (Registration $registration) => $registration->year->year.'|'.$registration->semester->order())
            ->map(fn (Registration $registration) => [
                'year' => $registration->year,
                'semester' => $registration->semester,
                'publication' => $publications->get($registration->year_id.'|'.$registration->semester->value),
                'pending' => $pending->get($registration->year_id.'|'.$registration->semester->value, 0),
            ])
            ->values();

        return view('livewire.admin.results.publication', ['terms' => $terms])
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
