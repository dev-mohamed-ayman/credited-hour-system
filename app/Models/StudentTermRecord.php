<?php

namespace App\Models;

use App\Enums\Student\StudentStatus;
use App\Enums\Student\StudyStatus;
use App\Enums\TermOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's line in a term closure: the standing it was decided on and
 * the before/after values, which is also what reverting the closure restores.
 */
class StudentTermRecord extends Model
{
    protected $fillable = [
        'term_closure_id',
        'student_id',
        'cgpa',
        'earned_hours',
        'outcome',
        'note',
        'warnings_before',
        'warnings_after',
        'issued_warning_id',
        'cleared_warning_ids',
        'level_id_before',
        'level_id_after',
        'study_status_before',
        'study_status_after',
        'status_before',
        'status_after',
    ];

    protected function casts(): array
    {
        return [
            'cgpa' => 'decimal:2',
            'earned_hours' => 'integer',
            'outcome' => TermOutcome::class,
            'warnings_before' => 'integer',
            'warnings_after' => 'integer',
            'cleared_warning_ids' => 'array',
            'study_status_before' => StudyStatus::class,
            'study_status_after' => StudyStatus::class,
            'status_before' => StudentStatus::class,
            'status_after' => StudentStatus::class,
        ];
    }

    public function closure(): BelongsTo
    {
        return $this->belongsTo(TermClosure::class, 'term_closure_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function levelBefore(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'level_id_before');
    }

    public function levelAfter(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'level_id_after');
    }
}
