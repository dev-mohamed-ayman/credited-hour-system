<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentWarning extends Model
{
    protected $fillable = [
        'student_id',
        'type',
        'reason',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => \App\Enums\Student\StudentWarningType::class,
            'is_active' => 'boolean',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
