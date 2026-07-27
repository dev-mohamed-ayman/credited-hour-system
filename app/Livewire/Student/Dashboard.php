<?php

namespace App\Livewire\Student;

use App\Enums\Student\StudentWarningType;
use App\Models\Grade;
use App\Models\Level;
use App\Models\Registration;
use App\Models\Setting;
use App\Models\Year;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class Dashboard extends Component
{
    public function calculateCumulativeStats(object $student): array
    {
        $student->loadMissing([
            'registrations' => fn ($q) => $q->with(['courses.course', 'courses.grade', 'year']),
        ]);

        $gradePoints = Grade::query()
            ->orderBy('order')
            ->pluck('order', 'id')
            ->all();

        $totalPoints = 0;
        $totalHoursForGPA = 0;
        $earnedHours = 0;
        $registeredHours = 0;

        foreach ($student->registrations as $registration) {
            foreach ($registration->courses as $courseReg) {
                $courseHours = (int) $courseReg->course->hours;
                $registeredHours += $courseHours;

                $gradeId = $courseReg->grade_id;
                if ($gradeId === null) {
                    continue;
                }

                $points = $gradePoints[$gradeId] ?? 0;

                if ($points > 0) {
                    $totalPoints += $courseHours * $points;
                    $totalHoursForGPA += $courseHours;
                    $earnedHours += $courseHours;
                }
            }
        }

        $cgpa = $totalHoursForGPA > 0 ? round($totalPoints / $totalHoursForGPA, 2) : 0;

        return [
            'cgpa' => $cgpa,
            'earned_hours' => $earnedHours,
            'registered_hours' => $registeredHours,
            'total_hours_for_gpa' => $totalHoursForGPA,
        ];
    }

    public function getGraduationRequiredHours(object $student): int
    {
        $settingHours = Setting::graduationRequiredHours();

        if ($settingHours > 0) {
            return $settingHours;
        }

        $maxLevel = (int) Level::query()->max('id');
        if ($maxLevel <= 0) {
            return 132;
        }

        return $maxLevel * 36;
    }

    public function getWarningThreshold(): float
    {
        return Setting::warningGpaThreshold();
    }

    public function getCurrentSemesterRegistration(object $student): ?Registration
    {
        $currentYear = Year::current();
        if (! $currentYear) {
            return null;
        }

        $currentSemester = $currentYear->getCurrentSemester();
        if (! $currentSemester) {
            return null;
        }

        return $student->registrations()
            ->where('year_id', $currentYear->id)
            ->where('semester', $currentSemester->value)
            ->first();
    }

    public function getActiveWarnings(object $student): Collection
    {
        return $student->warnings()
            ->where('is_active', true)
            ->latest()
            ->get();
    }

    public function getUnpaidFeeTickets(object $student): Collection
    {
        if (! method_exists($student, 'feeTickets')) {
            return collect();
        }

        try {
            return $student->feeTickets()
                ->unpaid()
                ->latest()
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    public function render(): View
    {
        $student = auth('student')->user();
        $student->loadMissing(['level', 'section', 'section.department', 'academicAdvisor']);

        $stats = $this->calculateCumulativeStats($student);
        $requiredHours = $this->getGraduationRequiredHours($student);
        $warningThreshold = $this->getWarningThreshold();
        $activeWarnings = $this->getActiveWarnings($student);
        $warningCount = $activeWarnings->count();
        $dangerCount = $activeWarnings->where('type', StudentWarningType::DANGER)->count();
        $warningOnlyCount = $activeWarnings->where('type', StudentWarningType::WARNING)->count();

        $currentRegistration = $this->getCurrentSemesterRegistration($student);
        $currentYear = Year::current();
        $currentSemester = $currentYear?->getCurrentSemester();
        $unpaidFeeTickets = $this->getUnpaidFeeTickets($student);
        $hasUnpaidFees = $unpaidFeeTickets->isNotEmpty();

        $walletService = app(\App\Services\WalletService::class);
        $walletBalance = $walletService->getBalance($student);
        $walletTransactions = $student->walletTransactions()->with('year')->latest()->take(5)->get();

        $cgpaColorClass = match (true) {
            $stats['cgpa'] >= 3 => 'bg-success',
            $stats['cgpa'] >= 2 => 'bg-warning',
            default => 'bg-danger',
        };

        $warningColorClass = match (true) {
            $dangerCount > 0 => 'text-danger',
            $warningOnlyCount > 0 => 'text-warning',
            default => 'text-success',
        };

        return view('livewire.student.dashboard', [
            'student' => $student,
            'stats' => $stats,
            'requiredHours' => $requiredHours,
            'warningThreshold' => $warningThreshold,
            'activeWarnings' => $activeWarnings,
            'warningCount' => $warningCount,
            'warningColorClass' => $warningColorClass,
            'dangerCount' => $dangerCount,
            'warningOnlyCount' => $warningOnlyCount,
            'currentRegistration' => $currentRegistration,
            'currentYear' => $currentYear,
            'currentSemester' => $currentSemester,
            'hasUnpaidFees' => $hasUnpaidFees,
            'unpaidFeeTickets' => $unpaidFeeTickets,
            'cgpaColorClass' => $cgpaColorClass,
            'walletBalance' => $walletBalance,
            'walletTransactions' => $walletTransactions,
        ])
            ->extends('student.layouts.app')
            ->section('content');
    }
}
