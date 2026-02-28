<?php

namespace App\Enums\Student;

enum StudyStatus: string
{
    case FRESHMAN = 'freshman';         // مستجد
    case REMAINING = 'remaining';       // باقي
    case EXTERNAL = 'external';         // من الخارج

    public function label(): string
    {
        return match ($this) {
            self::FRESHMAN => 'مستجد',
            self::REMAINING => 'باقي',
            self::EXTERNAL => 'من الخارج',
        };
    }
}
