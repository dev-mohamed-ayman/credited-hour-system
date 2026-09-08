<?php

namespace App\Enums;

/**
 * Schedulable weekdays: Saturday through Thursday (Friday is a holiday —
 * deliberately absent so it cannot be stored or selected).
 */
enum DayOfWeek: string
{
    case SATURDAY = 'saturday';
    case SUNDAY = 'sunday';
    case MONDAY = 'monday';
    case TUESDAY = 'tuesday';
    case WEDNESDAY = 'wednesday';
    case THURSDAY = 'thursday';

    public function label(): string
    {
        return match ($this) {
            self::SATURDAY => 'السبت',
            self::SUNDAY => 'الأحد',
            self::MONDAY => 'الاثنين',
            self::TUESDAY => 'الثلاثاء',
            self::WEDNESDAY => 'الأربعاء',
            self::THURSDAY => 'الخميس',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::SATURDAY => 1,
            self::SUNDAY => 2,
            self::MONDAY => 3,
            self::TUESDAY => 4,
            self::WEDNESDAY => 5,
            self::THURSDAY => 6,
        };
    }

    /**
     * @return array<string, string> value => label pairs for selects
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->sortBy(fn (self $case) => $case->order())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
