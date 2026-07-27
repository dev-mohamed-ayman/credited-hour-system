<?php

namespace App\Livewire\Admin\RegistrationRecord;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Services\CourseEligibilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class Show extends Component
{
    public Registration $registration;

    public ?int $selectedCourseId = null;

    public Collection $availableCourses;

    public ?string $rejectionReason = null;

    public function mount(Registration $registration, CourseEligibilityService $eligibilityService): void
    {
        $this->registration = $registration->loadMissing(['student.level', 'student.section.department', 'year', 'courses.course', 'courses.grade', 'approvedByUser', 'approvedByAdvisor']);

        $this->loadAvailableCourses($eligibilityService);
    }

    public function loadAvailableCourses(CourseEligibilityService $eligibilityService): void
    {
        $buckets = $eligibilityService->getBuckets(
            student: $this->registration->student,
            year: $this->registration->year,
            registrationSemester: $this->registration->semester,
            currentRegistration: $this->registration,
            isHistorical: true
        );

        $this->availableCourses = collect($buckets['retake'])
            ->merge($buckets['improvement'])
            ->merge($buckets['due'])
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    public function addCourse(CourseEligibilityService $eligibilityService): void
    {
        abort_unless(auth()->user()->can('course_registrations.create'), 403);

        $this->validate([
            'selectedCourseId' => 'required|exists:courses,id',
        ]);

        if (! $this->availableCourses->contains('id', $this->selectedCourseId)) {
            $this->dispatch('toast', message: 'هذه المادة غير متاحة للتسجيل في هذا السجل التاريخي.', type: 'error');

            return;
        }

        $pendingGradeId = $eligibilityService->getPendingGradeId();

        RegistrationCourse::create([
            'registration_id' => $this->registration->id,
            'course_id' => $this->selectedCourseId,
            'grade_id' => $pendingGradeId,
        ]);

        $this->registration->load('courses.course', 'courses.grade');
        $this->loadAvailableCourses($eligibilityService);
        $this->selectedCourseId = null;

        $this->dispatch('close-modal', id: 'addCourseModal');
        $this->dispatch('toast', message: 'تم إضافة المادة للسجل بنجاح.', type: 'success');
    }

    public function deleteCourse(int $registrationCourseId, CourseEligibilityService $eligibilityService): void
    {
        abort_unless(auth()->user()->can('course_registrations.delete'), 403);

        $registrationCourse = RegistrationCourse::query()->with(['course', 'registration.student'])->find($registrationCourseId);

        if (! $registrationCourse) {
            return;
        }

        $guard = $eligibilityService->canDeleteRegistrationCourse($registrationCourse);

        if (! $guard['allowed']) {
            $blockingCourses = implode(' و ', $guard['blocking_courses']);
            $this->dispatch('toast',
                message: "لا يمكن إزالة المادة. الطالب مسجل في مادة ({$blockingCourses}) في ترم لاحق بناءً على نجاحه في مادة ({$registrationCourse->course->name}).",
                type: 'error'
            );

            return;
        }

        $registrationCourse->delete();
        $this->registration->load('courses.course', 'courses.grade');
        $this->loadAvailableCourses($eligibilityService);

        $this->dispatch('toast', message: 'تم إزالة المادة من السجل بنجاح.', type: 'success');
    }

    public function approveRegistration(): void
    {
        abort_unless(auth()->user()->can('course_registrations.create'), 403);

        $walletService = app(\App\Services\WalletService::class);
        $cost = $walletService->calculateRegistrationCost($this->registration);

        if (! $walletService->hasEnoughBalance($this->registration->student, $cost)) {
            $this->dispatch('toast', message: 'رصيد المحفظة غير كافٍ لإتمام التسجيل.', type: 'error');

            return;
        }

        try {
            $walletService->deductRegistrationFees($this->registration, auth()->user());
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->registration->update([
            'status' => RegistrationStatus::APPROVED,
            'approved_by_user_id' => auth()->id(),
            'rejection_reason' => null,
        ]);

        $this->registration->load('approvedByUser');
        $this->dispatch('toast', message: 'تمت الموافقة على التسجيل بنجاح وتم خصم الرسوم.', type: 'success');
    }

    public function rejectRegistration(): void
    {
        abort_unless(auth()->user()->can('course_registrations.create'), 403);

        $this->validate([
            'rejectionReason' => 'required|string',
        ]);

        $this->registration->update([
            'status' => RegistrationStatus::REJECTED,
            'approved_by_user_id' => auth()->id(),
            'rejection_reason' => $this->rejectionReason,
        ]);

        $this->registration->load('approvedByUser');
        $this->rejectionReason = null;
        $this->dispatch('close-modal', id: 'rejectRegistrationModal');
        $this->dispatch('toast', message: 'تم رفض التسجيل بنجاح.', type: 'error');
    }

    public function render(): View
    {
        abort_unless(auth()->user()->can('course_registrations.view'), 403);

        return view('livewire.admin.registration-record.show')
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
