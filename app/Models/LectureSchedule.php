<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LectureSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'venue_id',
        'year_id',
        'day',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'day' => DayOfWeek::class,
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'lecture_schedule_section')->withTimestamps();
    }

    public function durationInMinutes(): int
    {
        return $this->timeToMinutes($this->end_time) - $this->timeToMinutes($this->start_time);
    }

    /**
     * Two sessions overlap when they share the same weekday and their time
     * ranges intersect. Back-to-back bookings (end == start) do NOT overlap.
     */
    public function overlaps(self $other): bool
    {
        if ($this->day !== $other->day) {
            return false;
        }

        return $this->timeToMinutes($this->start_time) < $this->timeToMinutes($other->end_time)
            && $this->timeToMinutes($this->end_time) > $this->timeToMinutes($other->start_time);
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);

        return ((int) $hours) * 60 + (int) $minutes;
    }
}
