<?php

namespace App\Enums;

enum MilitaryEducationCourseStatus: string
{
    case ACTIVE = 'active';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'مفتوح',
            self::CLOSED => 'مغل',
        };
    }
}
