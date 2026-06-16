<?php

namespace App\Enums;

enum SemesterStatus: string
{
    case OPEN_REGISTRATION = 'open_registration';
    case CLOSED_REGISTRATION = 'closed_registration';
    case DISABLED = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN_REGISTRATION => 'فتح التسجيل',
            self::CLOSED_REGISTRATION => 'غلق التسجيل',
            self::DISABLED => 'تعطيل',
        };
    }
}
