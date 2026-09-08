<?php

namespace App\Enums;

enum DiscountMode: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'مبلغ ثابت (ج.م)',
            self::Percentage => 'نسبة مئوية (٪)',
        };
    }
}
