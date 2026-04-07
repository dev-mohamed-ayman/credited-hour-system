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
}
