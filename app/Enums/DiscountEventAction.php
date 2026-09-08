<?php

namespace App\Enums;

enum DiscountEventAction: string
{
    case Granted = 'granted';
    case Edited = 'edited';
    case Revoked = 'revoked';
    case AutoRevoked = 'auto_revoked';

    public function label(): string
    {
        return match ($this) {
            self::Granted => 'منح',
            self::Edited => 'تعديل',
            self::Revoked => 'إلغاء',
            self::AutoRevoked => 'إلغاء تلقائي',
        };
    }
}
