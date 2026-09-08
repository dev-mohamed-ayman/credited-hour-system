<?php

namespace App\Services;

use App\Enums\DayOfWeek;
use App\Exceptions\LectureScheduleConflictException;
use App\Models\Course;
use App\Models\LectureSchedule;
use App\Models\RegistrationFee;
use App\Models\Section;
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
    /**
     * Single authoritative student count — shared by the Form's live preview
     * and the server-side capacity check so they can never drift (R1).
     *
     * @param  array<int, int|string>  $sectionIds
     */
    public function selectedStudentsCount(Course $course, array $sectionIds): int
    {
        $sections = Section::whereIn('id', $sectionIds)->withCount('students')->get();

        $fallback = null;
        $total = 0;

        foreach ($sections as $section) {
            $count = (int) $section->students_count;

            if ($count === 0) {
                $fallback ??= (int) (RegistrationFee::query()
                    ->where('department_id', $course->department_id)
                    ->where('level_id', $course->level_id)
                    ->value('number_of_students_per_section') ?? 0);

                $count = $fallback;
            }

            $total += $count;
        }

        return $total;
    }

    /**
     * @param  array<int, int|string>  $sectionIds
     */
    public function assertSectionsBelongToCourse(Course $course, array $sectionIds): void
    {
        if ($sectionIds === []) {
            throw new LectureScheduleConflictException('يجب اختيار شعبة واحدة على الأقل.');
        }

        $linked = $course->sections()->whereIn('sections.id', $sectionIds)->count();

        if ($linked !== count($sectionIds)) {
            throw new LectureScheduleConflictException('بعض الشعب المختارة غير مرتبطة بالمادة الدراسية.');
        }
    }

    /**
     * @param  array<int, int|string>  $sectionIds
     */
    public function assertVenueCapacity(Course $course, Venue $venue, array $sectionIds): void
    {
        if ($venue->capacity === null) {
            return;
        }

        $total = $this->selectedStudentsCount($course, $sectionIds);

        if ($total > $venue->capacity) {
            throw new LectureScheduleConflictException(
                "الإجمالي المختار {$total} طالب يتجاوز سعة {$venue->type->label()} ({$venue->capacity})"
            );
        }
    }

    /**
     * @param  array<int, int|string>  $sectionIds
     */
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
     * @param  array<int, int|string>  $sectionIds
     */
    public function assertNoSectionConflict(
        array $sectionIds,
        Course $course,
        DayOfWeek $day,
        string $start,
        string $end,
        ?int $yearId,
        ?int $ignoreScheduleId = null,
    ): void {
        $conflict = $this->overlappingQuery($course, $day, $start, $end, $yearId, $ignoreScheduleId)
            ->whereHas('sections', fn ($query) => $query->whereIn('sections.id', $sectionIds))
            ->with(['course:id,name', 'venue:id,name', 'sections:id,name'])
            ->first();

        if ($conflict !== null) {
            $clashing = $conflict->sections->whereIn('id', $sectionIds)->first();
            $name = $clashing?->name ?? 'غير معروفة';

            throw new LectureScheduleConflictException(
                "لا يمكن الحفظ: الشعبة ({$name}) لديها محاضرة {$conflict->day->label()} "
                ."من {$conflict->start_time} إلى {$conflict->end_time} في \"{$conflict->venue->name}\" "
                ."للمادة \"{$conflict->course->name}\""
            );
        }
    }

    /**
     * Full rule battery (FR-005..FR-013). Throws the first violation found.
     *
     * @param  array<int, int|string>  $sectionIds
     */
    public function validate(
        Course $course,
        Venue $venue,
        DayOfWeek $day,
        string $start,
        string $end,
        array $sectionIds,
        ?int $yearId,
        ?int $ignoreScheduleId = null,
    ): void {
        $sectionIds = $this->normalizeSectionIds($sectionIds);

        $this->assertValidTimeRange($start, $end);
        $this->assertSectionsBelongToCourse($course, $sectionIds);
        $this->assertVenueCapacity($course, $venue, $sectionIds);
        $this->assertNoVenueConflict($venue, $course, $day, $start, $end, $yearId, $ignoreScheduleId);
        $this->assertNoSectionConflict($sectionIds, $course, $day, $start, $end, $yearId, $ignoreScheduleId);
    }

    /**
     * @param  array{venue_id: int, day: DayOfWeek|string, start_time: string, end_time: string}  $attributes
     * @param  array<int, int|string>  $sectionIds
     */
    public function create(Course $course, array $attributes, array $sectionIds): LectureSchedule
    {
        $venue = Venue::findOrFail($attributes['venue_id']);
        $day = $attributes['day'] instanceof DayOfWeek ? $attributes['day'] : DayOfWeek::from($attributes['day']);
        $sectionIds = $this->normalizeSectionIds($sectionIds);

        return DB::transaction(function () use ($course, $venue, $day, $attributes, $sectionIds) {
            $this->lockRows($venue, $sectionIds);

            $yearId = Year::current()?->id;

            $this->validate($course, $venue, $day, $attributes['start_time'], $attributes['end_time'], $sectionIds, $yearId);

            $schedule = LectureSchedule::create([
                'course_id' => $course->id,
                'venue_id' => $venue->id,
                'year_id' => $yearId,
                'day' => $day,
                'start_time' => $attributes['start_time'],
                'end_time' => $attributes['end_time'],
            ]);

            $schedule->sections()->sync($sectionIds);

            return $schedule;
        });
    }

    /**
     * @param  array{venue_id: int, day: DayOfWeek|string, start_time: string, end_time: string}  $attributes
     * @param  array<int, int|string>  $sectionIds
     */
    public function update(LectureSchedule $schedule, array $attributes, array $sectionIds): void
    {
        $course = $schedule->course;
        $venue = Venue::findOrFail($attributes['venue_id']);
        $day = $attributes['day'] instanceof DayOfWeek ? $attributes['day'] : DayOfWeek::from($attributes['day']);
        $sectionIds = $this->normalizeSectionIds($sectionIds);

        DB::transaction(function () use ($schedule, $course, $venue, $day, $attributes, $sectionIds) {
            $this->lockRows($venue, $sectionIds);

            $this->validate(
                $course,
                $venue,
                $day,
                $attributes['start_time'],
                $attributes['end_time'],
                $sectionIds,
                $schedule->year_id,
                $schedule->id,
            );

            $schedule->update([
                'venue_id' => $venue->id,
                'day' => $day,
                'start_time' => $attributes['start_time'],
                'end_time' => $attributes['end_time'],
            ]);

            $schedule->sections()->sync($sectionIds);
        });
    }

    /**
     * @param  array<int, int|string>  $sectionIds
     */
    private function normalizeSectionIds(array $sectionIds): array
    {
        return array_values(array_unique(array_map('intval', $sectionIds)));
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

    /**
     * @param  array<int, int>  $sectionIds
     */
    private function lockRows(Venue $venue, array $sectionIds): void
    {
        Venue::whereKey($venue->id)->lockForUpdate()->first();
        Section::whereIn('id', $sectionIds)->lockForUpdate()->get();
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
