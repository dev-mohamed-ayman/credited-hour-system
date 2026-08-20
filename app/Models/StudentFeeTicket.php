<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Model;

class StudentFeeTicket extends Model
{
    protected $fillable = [
        'ticket_number',
        'student_id',
        'fee_type',
        'fee_id',
        'fee_name',
        'amount',
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
        ];
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
