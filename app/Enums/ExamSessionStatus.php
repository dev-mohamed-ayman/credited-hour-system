<?php

namespace App\Enums;

enum ExamSessionStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::PUBLISHED => 'منشور',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-label-secondary',
            self::PUBLISHED => 'bg-label-success',
        };
    }
}
