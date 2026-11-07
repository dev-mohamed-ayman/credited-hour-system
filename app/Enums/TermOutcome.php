<?php

namespace App\Enums;

/**
 * What closing a term did to one student.
 */
enum TermOutcome: string
{
    case WARNED = 'warned';
    case WARNING_REDUCED = 'warning_reduced';
    case NO_CHANGE = 'no_change';
    case FIRST_TERM = 'first_term';
    case PROMOTED = 'promoted';
    case HELD_BACK = 'held_back';
    case GRADUATED = 'graduated';
    case DISMISSED = 'dismissed';
    case EXCUSED = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::WARNED => 'إنذار',
            self::WARNING_REDUCED => 'تخفيض إنذار',
            self::NO_CHANGE => 'بدون تغيير',
            self::FIRST_TERM => 'أول ترم (لا يُحتسب)',
            self::PROMOTED => 'ترقية',
            self::HELD_BACK => 'تغيير حالة القيد',
            self::GRADUATED => 'تخرج',
            self::DISMISSED => 'فصل',
            self::EXCUSED => 'عذر / وقف قيد (لا يُحتسب)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WARNED, self::HELD_BACK => 'warning',
            self::DISMISSED => 'danger',
            self::PROMOTED, self::GRADUATED, self::WARNING_REDUCED => 'success',
            self::NO_CHANGE, self::FIRST_TERM, self::EXCUSED => 'secondary',
        };
    }
}
