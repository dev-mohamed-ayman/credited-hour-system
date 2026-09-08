<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSeatAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_session_id',
        'exam_committee_id',
        'student_id',
        'seat_number',
    ];

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
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
