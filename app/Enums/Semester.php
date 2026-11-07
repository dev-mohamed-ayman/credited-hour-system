<?php

namespace App\Enums;

enum Semester: string
{
    case FIRST = 'first';
    case SECOND = 'second';
    case SUMMER = 'summer';

    public function label(): string
    {
        return match ($this) {
            self::FIRST => 'الترم الأول',
            self::SECOND => 'الترم الثاني',
            self::SUMMER => 'الترم الصيفي',
        };
    }

    /**
     * Position within an academic year, for chronological sorting.
     */
    public function order(): int
    {
        return match ($this) {
            self::FIRST => 1,
            self::SECOND => 2,
            self::SUMMER => 3,
        };
    }
}
