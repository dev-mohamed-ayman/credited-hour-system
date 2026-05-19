<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationFee extends Model
{
    protected $fillable = [
        'department_id',
        'level_id',
        'hour_payment',
        'ministerial_payment',
        'hour_payment_remaining',
        'ministerial_payment_remaining',
        'total_student_payment',
        'student_registration_hour',
        'number_of_students_per_section',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }
}
