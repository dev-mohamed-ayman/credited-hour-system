<?php

namespace App\Enums;

enum TransferRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'قيد المراجعة',
            self::APPROVED => 'تمت الموافقة',
            self::REJECTED => 'مرفوض',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-label-warning',
            self::APPROVED => 'bg-label-success',
            self::REJECTED => 'bg-label-danger',
        };
    }
}
