<?php

namespace App\Enums;

enum ResultChangeSource: string
{
    case UPLOAD = 'upload';
    case MANUAL = 'manual';
    case EXCUSE = 'excuse';

    public function label(): string
    {
        return match ($this) {
            self::UPLOAD => 'رفع شيت',
            self::MANUAL => 'تعديل يدوي',
            self::EXCUSE => 'عذر',
        };
    }
}
