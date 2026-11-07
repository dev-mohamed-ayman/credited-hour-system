<?php

namespace App\Enums\Student;

enum ExcuseType: string
{
    case COURSE = 'course';             // عذر عن مادة أو أكثر
    case TERM = 'term';                 // عذر عن ترم
    case SUSPENSION = 'suspension';     // وقف قيد (السنة كلها)

    public function label(): string
    {
        return match ($this) {
            self::COURSE => 'عذر عن مادة أو أكثر',
            self::TERM => 'عذر عن ترم',
            self::SUSPENSION => 'وقف قيد',
        };
    }

    /**
     * The general student status this excuse puts the student in, if any.
     */
    public function studentStatus(): ?StudentStatus
    {
        return match ($this) {
            self::COURSE => null,
            self::TERM => StudentStatus::EXCUSED,
            self::SUSPENSION => StudentStatus::SUSPENDED,
        };
    }
}
