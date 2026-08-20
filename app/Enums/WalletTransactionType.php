<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case DEPOSIT = 'deposit';
    case WITHDRAWAL = 'withdrawal';
    case REFUND = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::DEPOSIT => 'إيداع',
            self::WITHDRAWAL => 'سحب',
            self::REFUND => 'استرداد',
        };
    }
}
