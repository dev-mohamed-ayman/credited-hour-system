<?php

namespace App\Services;

use App\Enums\DayOfWeek;
use App\Exceptions\LectureScheduleConflictException;
use App\Models\Course;
use App\Models\LectureSchedule;
use App\Models\Venue;
use App\Models\Year;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Owns every lecture-scheduling business rule (BR-2..BR-5, BR-11, FR-020).
 * Livewire components orchestrate; this service decides.
 */
class LectureScheduleService
{
    public function __construct(public StudentSectionDistributionService $distribution) {}

    /**
     * Single authoritative student count — shared by the Form's live preview
     * and the server-side capacity check so they can never drift (R1).
     *
     * @param  array<int, int|string>  $sectionNumbers
     */
    public function selectedStudentsCount(Course $course, array $sectionNumbers): int
    {
        $sectionNumbers = $this->normalizeSectionNumbers($sectionNumbers);

        if ($sectionNumbers === []) {
            return 0;
        }

        return $this->distribution
            ->groupQuery($course->department_id, $course->level_id)
            ->whereIn('section_number', $sectionNumbers)
            ->count();
    }

    public function sectionsCount(Course $course): int
    {
        return $this->distribution->sectionsCount($course->department_id, $course->level_id);
    }

    /**
     * @param  array<int, int>  $sectionNumbers
     */
    public function assertValidSectionNumbers(Course $course, array $sectionNumbers): void
    {
        $sectionsCount = $this->sectionsCount($course);

        if ($sectionsCount === 0) {
            throw new LectureScheduleConflictException(
                'لم يتم توزيع طلاب هذه الفرقة على السكاشن بعد — قم بالتوزيع من إعدادات مصاريف التسجيل.'
            );
        }

        if ($sectionNumbers === []) {
            throw new LectureScheduleConflictException('يجب اختيار سكشن واحد على الأقل.');
        }

        if (min($sectionNumbers) < 1 || max($sectionNumbers) > $sectionsCount) {
            throw new LectureScheduleConflictException("عدد السكاشن المتاحة لهذه الفرقة {$sectionsCount} سكشن فقط.");
        }
    }

    /**
     * @param  array<int, int>  $sectionNumbers
     */
    public function assertVenueCapacity(Course $course, Venue $venue, array $sectionNumbers): void
    {
        if ($venue->capacity === null) {
            return;
        }

        $total = $this->selectedStudentsCount($course, $sectionNumbers);

        if ($total > $venue->capacity) {
            throw new LectureScheduleConflictException(
                "الإجمالي المختار {$total} طالب يتجاوز سعة {$venue->type->label()} ({$venue->capacity})"
            );
        }
    }

    public function assertNoVenueConflict(
        Venue $venue,
        Course $course,
        DayOfWeek $day,
        string $start,
        string $end,
        ?int $yearId,
        ?int $ignoreScheduleId = null,
    ): void {
        $conflict = $this->overlappingQuery($course, $day, $start, $end, $yearId, $ignoreScheduleId)
            ->where('venue_id', $venue->id)
            ->with(['course:id,name', 'venue:id,name'])
            ->first();

        if ($conflict !== null) {
            throw new LectureScheduleConflictException(
                "لا يمكن الحفظ: المكان \"{$conflict->venue->name}\" محجوز {$conflict->day->label()} "
                ."من {$conflict->start_time} إلى {$conflict->end_time} للمادة \"{$conflict->course->name}\""
            );
        }
    }

    /**
     * Section numbers are scoped per department/level, so only sessions of
     * courses in the same department/level sharing a section number can clash.
     *
     * @param  array<int, int>  $sectionNumbers
     */
    public function assertNoSectionConflict(
        array $sectionNumbers,
        Course $course,
        DayOfWeek $day,
        string $start,
        string $end,
        ?int $yearId,
        ?int $ignoreScheduleId = null,
    ): void {
        $clashing = [];

        $conflict = $this->overlappingQuery($course, $day, $start, $end, $yearId, $ignoreScheduleId)
            ->whereHas('course', fn ($query) => $query
                ->where('department_id', $course->department_id)
                ->where('level_id', $course->level_id))
            ->with(['course:id,name', 'venue:id,name'])
            ->get()
            ->first(function (LectureSchedule $schedule) use ($sectionNumbers, &$clashing) {
                $clashing = array_values(array_intersect($sectionNumbers, $this->normalizeSectionNumbers($schedule->section_numbers ?? [])));

                return $clashing !== [];
            });

        if ($conflict !== null) {
            $label = (new LectureSchedule(['section_numbers' => $clashing]))->sectionNumbersLabel();

            throw new LectureScheduleConflictException(
                "لا يمكن الحفظ: {$label} لديها محاضرة {$conflict->day->label()} "
                ."من {$conflict->start_time} إلى {$conflict->end_time} في \"{$conflict->venue->name}\" "
                ."للمادة \"{$conflict->course->name}\""
            );
        }
    }

    /**
     * Full rule battery (FR-005..FR-013). Throws the first violation found.
     *
     * @param  array<int, int|string>  $sectionNumbers
     */
    public function validate(
        Course $course,
        Venue $venue,
        DayOfWeek $day,
        string $start,
        string $end,
        array $sectionNumbers,
        ?int $yearId,
        ?int $ignoreScheduleId = null,
    ): void {
        $sectionNumbers = $this->normalizeSectionNumbers($sectionNumbers);

        $this->assertValidTimeRange($start, $end);
        $this->assertValidSectionNumbers($course, $sectionNumbers);
        $this->assertVenueCapacity($course, $venue, $sectionNumbers);
        $this->assertNoVenueConflict($venue, $course, $day, $start, $end, $yearId, $ignoreScheduleId);
        $this->assertNoSectionConflict($sectionNumbers, $course, $day, $start, $end, $yearId, $ignoreScheduleId);
    }

    /**
     * @param  array{venue_id: int, day: DayOfWeek|string, start_time: string, end_time: string, section_numbers: array<int, int|string>}  $attributes
     */
    public function create(Course $course, array $attributes): LectureSchedule
    {
        $venue = Venue::findOrFail($attributes['venue_id']);
        $day = $attributes['day'] instanceof DayOfWeek ? $attributes['day'] : DayOfWeek::from($attributes['day']);
        $sectionNumbers = $this->normalizeSectionNumbers($attributes['section_numbers']);

        return DB::transaction(function () use ($course, $venue, $day, $attributes, $sectionNumbers) {
            $this->lockRows($venue);

            $yearId = Year::current()?->id;

            $this->validate($course, $venue, $day, $attributes['start_time'], $attributes['end_time'], $sectionNumbers, $yearId);

            return LectureSchedule::create([
                'course_id' => $course->id,
                'venue_id' => $venue->id,
                'year_id' => $yearId,
                'day' => $day,
                'start_time' => $attributes['start_time'],
                'end_time' => $attributes['end_time'],
                'section_numbers' => $sectionNumbers,
            ]);
        });
    }

    /**
     * @param  array{venue_id: int, day: DayOfWeek|string, start_time: string, end_time: string, section_numbers: array<int, int|string>}  $attributes
     */
    public function update(LectureSchedule $schedule, array $attributes): void
    {
        $course = $schedule->course;
        $venue = Venue::findOrFail($attributes['venue_id']);
        $day = $attributes['day'] instanceof DayOfWeek ? $attributes['day'] : DayOfWeek::from($attributes['day']);
        $sectionNumbers = $this->normalizeSectionNumbers($attributes['section_numbers']);

        DB::transaction(function () use ($schedule, $course, $venue, $day, $attributes, $sectionNumbers) {
            $this->lockRows($venue);

            $this->validate(
                $course,
                $venue,
                $day,
                $attributes['start_time'],
                $attributes['end_time'],
                $sectionNumbers,
                $schedule->year_id,
                $schedule->id,
            );

            $schedule->update([
                'venue_id' => $venue->id,
                'day' => $day,
                'start_time' => $attributes['start_time'],
                'end_time' => $attributes['end_time'],
                'section_numbers' => $sectionNumbers,
            ]);
        });
    }

    /**
     * @param  array<int, int|string>  $sectionNumbers
     * @return array<int, int>
     */
    public function normalizeSectionNumbers(array $sectionNumbers): array
    {
        $normalized = array_values(array_unique(array_map('intval', $sectionNumbers)));
        sort($normalized);

        return $normalized;
    }

    private function assertValidTimeRange(string $start, string $end): void
    {
        foreach (['start_time' => $start, 'end_time' => $end] as $label => $time) {
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m) !== 1) {
                throw new LectureScheduleConflictException('تنسيق الوقت يجب أن يكون HH:MM');
            }

            if (((int) $m[2]) % 15 !== 0) {
                throw new LectureScheduleConflictException(
                    'أوقات البداية والنهاية يجب أن تكون بمضاعفات ربع ساعة (15 دقيقة)'
                );
            }
        }

        if ($this->timeToMinutes($end) <= $this->timeToMinutes($start)) {
            throw new LectureScheduleConflictException('يجب أن يكون وقت النهاية أكبر من وقت البداية');
        }
    }

    /**
     * Shared overlap scope: same weekday, same year, same semester (derived
     * from the course — never stored on the session), excluding self on edit.
     */
    private function overlappingQuery(
        Course $course,
        DayOfWeek $day,
        string $start,
        string $end,
        ?int $yearId,
        ?int $ignoreScheduleId = null,
    ): Builder {
        return LectureSchedule::query()
            ->where('day', $day->value)
            ->where('year_id', $yearId)
            ->where('start_time', '<', $this->padTime($end))
            ->where('end_time', '>', $this->padTime($start))
            ->whereHas('course', fn ($query) => $query->where('semester', $course->semester))
            ->when($ignoreScheduleId, fn ($query) => $query->whereKeyNot($ignoreScheduleId));
    }

    private function lockRows(Venue $venue): void
    {
        Venue::whereKey($venue->id)->lockForUpdate()->first();
    }

    private function padTime(string $time): string
    {
        [$hours, $minutes] = explode(':', $time);

        return str_pad($hours, 2, '0', STR_PAD_LEFT).':'.$minutes;
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $this->padTime($time));

        return ((int) $hours) * 60 + (int) $minutes;
    }
}
