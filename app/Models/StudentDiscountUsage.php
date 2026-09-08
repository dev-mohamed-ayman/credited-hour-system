<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDiscountUsage extends Model
{
    protected $fillable = [
        'student_discount_id',
        'student_fee_ticket_id',
        'applied_amount',
        'applied_by',
    ];

    protected function casts(): array
    {
        return [
            'applied_amount' => 'decimal:2',
        ];
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(StudentDiscount::class, 'student_discount_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(StudentFeeTicket::class, 'student_fee_ticket_id');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
