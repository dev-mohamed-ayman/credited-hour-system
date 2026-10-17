<?php

namespace App\Enums;

/**
 * Non-numeric outcomes a control sheet may carry in place of marks.
 * Any of them makes the course result an F.
 */
enum ExamResultStatus: string
{
    case ABSENT = 'absent';
    case DISCIPLINARY = 'disciplinary';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ABSENT => 'غائب',
            self::DISCIPLINARY => 'تأديب',
            self::CANCELLED => 'الغاء',
        };
    }

    /**
     * Resolves the Arabic word written in a result sheet cell, tolerating the
     * common hamza / alef spelling variants.
     */
    public static function fromSheetValue(string $value): ?self
    {
        $normalized = str_replace(['أ', 'إ', 'آ'], 'ا', trim($value));

        return match ($normalized) {
            'غائب', 'غايب' => self::ABSENT,
            'تاديب' => self::DISCIPLINARY,
            'الغاء' => self::CANCELLED,
            default => null,
        };
    }
}
