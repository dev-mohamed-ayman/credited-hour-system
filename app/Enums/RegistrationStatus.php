<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'قيد الموافقة',
            self::APPROVED => 'تمت الموافقة',
            self::REJECTED => 'مرفوض',
        };
    }
}
