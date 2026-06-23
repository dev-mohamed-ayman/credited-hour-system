<?php

namespace App\Support;

use App\Enums\Semester;

class CourseSemesterMapper
{
    /**
     * @var array<string, Semester>
     */
    private const ARABIC_TO_ENUM = [
        'الأول' => Semester::FIRST,
        'الثاني' => Semester::SECOND,
        'الصيفي' => Semester::SUMMER,
    ];

    public static function toEnum(string $arabicSemester): ?Semester
    {
        return self::ARABIC_TO_ENUM[$arabicSemester] ?? null;
    }

    public static function sequence(Semester|string|null $semester): int
    {
        if ($semester === null) {
            return 0;
        }

        if (is_string($semester)) {
            $semester = Semester::tryFrom($semester) ?? self::toEnum($semester);
        }

        return match ($semester) {
            Semester::FIRST => 1,
            Semester::SECOND => 2,
            Semester::SUMMER => 3,
            default => 0,
        };
    }

    public static function fromCourseSemester(string $courseSemester): int
    {
        return self::sequence(self::toEnum($courseSemester));
    }
}
