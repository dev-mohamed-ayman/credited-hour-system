<?php

namespace App\Models;

use App\Enums\ExamSessionStatus;
use App\Enums\ExamType;
use App\Enums\Semester;
use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSession extends Model
{
    use HasDeletionGuards, HasFactory;

    protected $fillable = [
        'course_id',
        'year_id',
        'semester',
        'type',
        'exam_date',
        'start_time',
        'end_time',
        'status',
        'notes',
    ];

    protected $blockingRelations = ['committees', 'seatAssignments'];

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'type' => ExamType::class,
            'status' => ExamSessionStatus::class,
            'exam_date' => 'date:Y-m-d',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function committees(): HasMany
    {
        return $this->hasMany(ExamCommittee::class);
    }

    public function seatAssignments(): HasMany
    {
        return $this->hasMany(ExamSeatAssignment::class);
    }

    /**
     * Two exam sessions conflict when they fall on the same date and their
     * time ranges intersect. Back-to-back (end == start) does NOT overlap.
     */
    public function overlaps(self $other): bool
    {
        if ($this->exam_date->toDateString() !== $other->exam_date->toDateString()) {
            return false;
        }

        return $this->timeToMinutes($this->start_time) < $this->timeToMinutes($other->end_time)
            && $this->timeToMinutes($this->end_time) > $this->timeToMinutes($other->start_time);
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', substr($time, 0, 5));

        return ((int) $hours) * 60 + (int) $minutes;
    }
}
