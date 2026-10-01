<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamCommitteeStudent extends Model
{
    /** @use HasFactory<\Database\Factories\ExamCommitteeStudentFactory> */
    use HasFactory;

    protected $fillable = [
        'exam_committee_id',
        'student_id',
        'seat_number',
    ];

    protected function casts(): array
    {
        return [
            'seat_number' => 'integer',
        ];
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(ExamCommittee::class, 'exam_committee_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
