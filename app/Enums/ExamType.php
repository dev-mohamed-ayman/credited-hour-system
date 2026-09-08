<?php

namespace App\Enums;

enum ExamType: string
{
    case REGULAR = 'regular';
    case RESIT = 'resit';
    case IMPROVEMENT = 'improvement';

    public function label(): string
    {
        return match ($this) {
            self::REGULAR => 'عادي',
            self::RESIT => 'فصل ثانٍ',
            self::IMPROVEMENT => 'تحسين',
        };
    }
}
