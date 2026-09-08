<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentFeeTicket extends Model
{
    protected $fillable = [
        'ticket_number',
        'student_id',
        'fee_type',
        'fee_id',
        'fee_name',
        'amount',
        'original_amount',
        'discount_amount',
        'status',
        'ministerial_receipt_number',
        'payment_method',
        'visa_last_four',
        'paid_at',
        'notes',
        'year_id',
        'semester',
        'department_id',
        'level_id',
        'section_id',
        'gender',
        'fee_details',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'semester' => Semester::class,
            'fee_details' => 'array',
            'amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
        ];
    }

    public function discountUsages(): HasMany
    {
        return $this->hasMany(StudentDiscountUsage::class, 'student_fee_ticket_id');
    }

    /**
     * The gross value before any discount: original snapshot when discounted, net otherwise.
     */
    public function grossAmount(): float
    {
        return (float) ($this->original_amount ?? $this->amount);
    }

    public function hasDiscount(): bool
    {
        return $this->original_amount !== null && (float) $this->discount_amount > 0;
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->paid_at !== null;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'pending');
    }

    public function year()
    {
        return $this->belongsTo(Year::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function fee()
    {
        if ($this->fee_type === 'additional') {
            return $this->belongsTo(AdditionalFee::class, 'fee_id');
        }

        return $this->belongsTo(RegistrationFee::class, 'fee_id');
    }
}
