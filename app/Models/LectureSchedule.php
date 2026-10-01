<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'section_numbers',
    ];

    protected function casts(): array
    {
        return [
            'day' => DayOfWeek::class,
            'section_numbers' => 'array',
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

    /**
     * Human label that collapses consecutive numbers: "سكاشن 1-3، 5، 7-9".
     */
    public function sectionNumbersLabel(): string
    {
        $numbers = collect($this->section_numbers ?? [])->map(fn ($number) => (int) $number)->unique()->sort()->values();

        if ($numbers->isEmpty()) {
            return 'غير محدد';
        }

        $groups = [];
        $start = $previous = $numbers->first();

        foreach ($numbers->slice(1) as $number) {
            if ($number === $previous + 1) {
                $previous = $number;

                continue;
            }

            $groups[] = $start === $previous ? (string) $start : "{$start}-{$previous}";
            $start = $previous = $number;
        }

        $groups[] = $start === $previous ? (string) $start : "{$start}-{$previous}";

        return ($numbers->count() === 1 ? 'سكشن ' : 'سكاشن ').implode('، ', $groups);
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
