<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFeeTicket extends Model
{
    protected $fillable = [
        'ticket_number',
        'student_id',
        'fee_type',
        'fee_id',
        'amount',
        'status',
        'ministerial_receipt_number',
        'payment_method',
        'visa_last_four',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
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
