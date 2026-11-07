<?php

namespace App\Models;

use App\Enums\Semester;
use App\Enums\Student\ExcuseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An excuse for one term (عذر عن مادة / عذر عن ترم), or a suspension (وقف قيد)
 * recorded in the first term and covering the whole academic year.
 */
class StudentExcuse extends Model
{
    protected $fillable = [
        'student_id',
        'year_id',
        'semester',
        'type',
        'reason',
        'excused_course_ids',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'type' => ExcuseType::class,
            'excused_course_ids' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether the excuse spares the student the warning of this term.
     */
    public function excusesTerm(int $yearId, Semester $semester): bool
    {
        return (int) $this->year_id === $yearId
            && ($this->type === ExcuseType::SUSPENSION || ($this->type === ExcuseType::TERM && $this->semester === $semester));
    }
}
