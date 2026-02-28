<?php

namespace App\Enums\Student;

enum StudentStatus: string
{
    case REGISTERED = 'registered';     // مقيد
    case EXCUSED = 'excused';           // عذر
    case SUSPENDED = 'suspended';       // وقف قيد
    case WITHDRAWN = 'withdrawn';       // سحب ملف
    case DISMISSED = 'dismissed';       // مفصول
    case GRADUATED = 'graduated';       // خريج

    public function label(): string
    {
        return match ($this) {
            self::REGISTERED => 'مقيد',
            self::EXCUSED => 'عذر',
            self::SUSPENDED => 'وقف قيد',
            self::WITHDRAWN => 'سحب ملف',
            self::DISMISSED => 'مفصول',
            self::GRADUATED => 'خريج',
        };
    }
}
