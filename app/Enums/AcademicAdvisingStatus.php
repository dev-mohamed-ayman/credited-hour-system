<?php

namespace App\Enums;

enum AcademicAdvisingStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'فتح التسجيل',
            self::CLOSED => 'غلق التسجيل',
        };
    }
}
