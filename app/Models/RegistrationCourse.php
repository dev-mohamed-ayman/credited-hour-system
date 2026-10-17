<?php

namespace App\Models;

use App\Enums\ExamResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationCourse extends Model
{
    protected $fillable = [
        'registration_id',
        'course_id',
        'grade_id',
        'written_score',
        'coursework_score',
        'total_score',
        'exam_status',
        'result_note',
        'result_uploaded_at',
        'result_uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'written_score' => 'decimal:2',
            'coursework_score' => 'decimal:2',
            'total_score' => 'decimal:2',
            'exam_status' => ExamResultStatus::class,
            'result_uploaded_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function resultUploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'result_uploaded_by');
    }
}
