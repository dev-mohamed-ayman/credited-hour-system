<?php

namespace App\Enums;

enum DiscountStatus: string
{
    case Active = 'active';
    case PartiallyApplied = 'partially_applied';
    case Exhausted = 'exhausted';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'نشط',
            self::PartiallyApplied => 'مطبَّق جزئيًا',
            self::Exhausted => 'مستنفَد',
            self::Revoked => 'ملغى',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'bg-label-success',
            self::PartiallyApplied => 'bg-label-warning',
            self::Exhausted => 'bg-label-secondary',
            self::Revoked => 'bg-label-danger',
        };
    }
}
