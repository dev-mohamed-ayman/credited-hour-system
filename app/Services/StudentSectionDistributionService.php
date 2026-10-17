<?php

namespace App\Services;

use App\Enums\Student\StudentStatus;
use App\Models\RegistrationFee;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Assigns each student of a department/level a numbered section (سكشن).
 * Numbers start at 1 per department/level and each section holds at most
 * the configured "students per section" from the registration fee settings.
 */
class StudentSectionDistributionService
{
    /**
     * Statuses whose students no longer attend lectures and are never distributed.
     *
     * @var array<int, StudentStatus>
     */
    private const INACTIVE_STATUSES = [
        StudentStatus::WITHDRAWN,
        StudentStatus::DISMISSED,
        StudentStatus::GRADUATED,
    ];

    /**
     * Students attending lectures for the given department/level.
     *
     * @return Builder<Student>
     */
    public function groupQuery(int $departmentId, int $levelId): Builder
    {
        return Student::query()
            ->where('level_id', $levelId)
            ->whereHas('section', fn (Builder $query) => $query->where('department_id', $departmentId))
            ->where(fn (Builder $query) => $query
                ->whereNull('status')
                ->orWhereNotIn('status', array_map(fn (StudentStatus $status) => $status->value, self::INACTIVE_STATUSES)));
    }

    public function studentsPerSection(int $departmentId, int $levelId): int
    {
        return (int) (RegistrationFee::query()
            ->where('department_id', $departmentId)
            ->where('level_id', $levelId)
            ->value('number_of_students_per_section') ?? 0);
    }

    public function sectionsCount(int $departmentId, int $levelId): int
    {
        return (int) ($this->groupQuery($departmentId, $levelId)->max('section_number') ?? 0);
    }

    /**
     * @return array<int, int> section number => students count
     */
    public function studentsCountPerSection(int $departmentId, int $levelId): array
    {
        return $this->groupQuery($departmentId, $levelId)
            ->whereNotNull('section_number')
            ->selectRaw('section_number, count(*) as students_count')
            ->groupBy('section_number')
            ->orderBy('section_number')
            ->pluck('students_count', 'section_number')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return array{total: int, distributed: int, undistributed: int, sections: int, per_section: int}
     */
    public function summary(int $departmentId, int $levelId): array
    {
        $total = $this->groupQuery($departmentId, $levelId)->count();
        $undistributed = $this->groupQuery($departmentId, $levelId)->whereNull('section_number')->count();

        return [
            'total' => $total,
            'distributed' => $total - $undistributed,
            'undistributed' => $undistributed,
            'sections' => $this->sectionsCount($departmentId, $levelId),
            'per_section' => $this->studentsPerSection($departmentId, $levelId),
        ];
    }

    /**
     * Distribute the not-yet-distributed students: free seats in existing
     * sections are filled first (lowest number first), then new sections are
     * opened. Returns how many students were assigned.
     */
    public function distribute(int $departmentId, int $levelId): int
    {
        $perSection = $this->studentsPerSection($departmentId, $levelId);

        if ($perSection <= 0) {
            throw new InvalidArgumentException('يجب تحديد عدد الطلاب في السكشن أولًا من إعدادات مصاريف التسجيل.');
        }

        return DB::transaction(function () use ($departmentId, $levelId, $perSection) {
            $pending = $this->groupQuery($departmentId, $levelId)
                ->whereNull('section_number')
                ->orderBy('name')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            if ($pending->isEmpty()) {
                return 0;
            }

            $counts = $this->studentsCountPerSection($departmentId, $levelId);
            $sectionNumber = 1;
            $assignments = [];

            foreach ($pending as $studentId) {
                $sectionNumber = $this->firstSectionWithFreeSeat($counts, $perSection, $sectionNumber);

                $assignments[$sectionNumber][] = $studentId;
                $counts[$sectionNumber] = ($counts[$sectionNumber] ?? 0) + 1;
            }

            foreach ($assignments as $number => $studentIds) {
                Student::whereKey($studentIds)->update(['section_number' => $number]);
            }

            return $pending->count();
        });
    }

    /**
     * Place a single not-yet-distributed student in the first section with a
     * free seat of their department/level. Students already distributed,
     * inactive students, and groups without a configured section size are
     * left untouched. Returns the assigned section number, if any.
     */
    public function assign(Student $student): ?int
    {
        return DB::transaction(function () use ($student) {
            $student = Student::query()->with('section')->lockForUpdate()->find($student->id);

            if (! $student || $student->section_number !== null || ! $student->level_id || ! $student->section) {
                return null;
            }

            $departmentId = (int) $student->section->department_id;
            $levelId = (int) $student->level_id;

            if (! $this->groupQuery($departmentId, $levelId)->whereKey($student->id)->exists()) {
                return null;
            }

            $perSection = (int) (RegistrationFee::query()
                ->where('department_id', $departmentId)
                ->where('level_id', $levelId)
                ->lockForUpdate()
                ->value('number_of_students_per_section') ?? 0);

            if ($perSection <= 0) {
                return null;
            }

            $sectionNumber = $this->firstSectionWithFreeSeat(
                $this->studentsCountPerSection($departmentId, $levelId),
                $perSection,
            );

            $student->forceFill(['section_number' => $sectionNumber])->save();

            return $sectionNumber;
        });
    }

    /**
     * @param  array<int, int>  $counts  section number => students count
     */
    private function firstSectionWithFreeSeat(array $counts, int $perSection, int $startFrom = 1): int
    {
        $sectionNumber = $startFrom;

        while (($counts[$sectionNumber] ?? 0) >= $perSection) {
            $sectionNumber++;
        }

        return $sectionNumber;
    }
}
