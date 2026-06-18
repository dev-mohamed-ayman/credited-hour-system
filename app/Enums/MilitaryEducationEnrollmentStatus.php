<?php

namespace App\Enums;

enum MilitaryEducationEnrollmentStatus: string
{
    case REGISTERED = 'registered';
    case PASSED = 'passed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::REGISTERED => 'مسجل',
            self::PASSED => 'ناجح',
            self::FAILED => 'راسب',
        };
    }
}
