<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentWallet extends Model
{
    protected $fillable = [
        'student_id',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
