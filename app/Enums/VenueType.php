<?php

namespace App\Enums;

enum VenueType: string
{
    case AUDITORIUM = 'auditorium';
    case LAB = 'lab';
    case CLASSROOM = 'classroom';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::AUDITORIUM => 'مدرج',
            self::LAB => 'معمل',
            self::CLASSROOM => 'قاعة دراسية',
            self::OTHER => 'أخرى',
        };
    }

    /**
     * @return array<string, string> value => label pairs for selects
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
