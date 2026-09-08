<?php

namespace App\Enums;

enum DiscountScope: string
{
    case Registration = 'registration';
    case Additional = 'additional';
    case Any = 'any';

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'رسوم التسجيل',
            self::Additional => 'رسوم إضافية محددة',
            self::Any => 'أي رسوم',
        };
    }
}
