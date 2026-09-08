<?php

namespace App\Models;

use App\Enums\DiscountEventAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDiscountEvent extends Model
{
    protected $fillable = [
        'student_discount_id',
        'action',
        'meta',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'action' => DiscountEventAction::class,
            'meta' => 'array',
        ];
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(StudentDiscount::class, 'student_discount_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
