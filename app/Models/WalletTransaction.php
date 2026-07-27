<?php

namespace App\Models;

use App\Enums\Semester;
use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'student_id',
        'year_id',
        'semester',
        'amount',
        'type',
        'reason',
        'reference_type',
        'reference_id',
        'performed_by_type',
        'performed_by_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'type' => WalletTransactionType::class,
        'semester' => Semester::class,
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function year()
    {
        return $this->belongsTo(Year::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function performedBy()
    {
        return $this->morphTo();
    }
}
