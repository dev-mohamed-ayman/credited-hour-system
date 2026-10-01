<?php

namespace App\Models;

use App\Enums\ExamType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One exam (course + date + time) inside a committee's timetable.
 */
class ExamSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_committee_id',
        'course_id',
        'type',
        'exam_date',
        'start_time',
        'end_time',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExamType::class,
            'exam_date' => 'date:Y-m-d',
        ];
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(ExamCommittee::class, 'exam_committee_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Two exams conflict when they fall on the same date and their time
     * ranges intersect. Back-to-back (end == start) does NOT overlap.
     */
    public function overlaps(self $other): bool
    {
        if ($this->exam_date->toDateString() !== $other->exam_date->toDateString()) {
            return false;
        }

        return $this->timeToMinutes($this->start_time) < $this->timeToMinutes($other->end_time)
            && $this->timeToMinutes($this->end_time) > $this->timeToMinutes($other->start_time);
    }

    public function timeRangeLabel(): string
    {
        return substr((string) $this->start_time, 0, 5).' – '.substr((string) $this->end_time, 0, 5);
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', substr($time, 0, 5));

        return ((int) $hours) * 60 + (int) $minutes;
    }
}
