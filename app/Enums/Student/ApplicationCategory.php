<?php

namespace App\Enums\Student;

enum ApplicationCategory: string
{
    case NOMINATED = 'nominated';   // مرشح
    case TRANSFERRED = 'transferred'; // محول
    case DIRECT = 'direct';         // مباشر
    case DISMISSED = 'dismissed';   // مفصول

    public function label(): string
    {
        return match ($this) {
            self::NOMINATED => 'مرشح',
            self::TRANSFERRED => 'محول',
            self::DIRECT => 'مباشر',
            self::DISMISSED => 'مفصول',
        };
    }
}
