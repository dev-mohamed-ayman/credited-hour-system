<?php

namespace App\Enums\Student;

enum StudentWarningType: string
{
    case WARNING = 'warning';
    case DANGER = 'danger';

    public function label(): string
    {
        return match ($this) {
            self::WARNING => 'تحذير',
            self::DANGER => 'خطر',
        };
    }
}
