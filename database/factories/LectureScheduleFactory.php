<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\Course;
use App\Models\Department;
use App\Models\LectureSchedule;
use App\Models\Level;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LectureSchedule>
 */
class LectureScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $slotIndex = 0;

        $slots = [
            ['09:00', '10:30'],
            ['11:00', '12:30'],
            ['13:00', '14:30'],
        ];

        [$start, $end] = $slots[$slotIndex % count($slots)];
        $slotIndex++;

        return [
            'course_id' => fn () => $this->resolveCourse()->id,
            'venue_id' => Venue::factory(),
            'year_id' => null,
            'day' => DayOfWeek::cases()[$slotIndex % count(DayOfWeek::cases())],
            'start_time' => $start,
            'end_time' => $end,
        ];
    }

    private function resolveCourse(): Course
    {
        $department = Department::firstOrCreate(
            ['code' => 'SCHED-DEPT'],
            ['name' => 'تخصص تجريبي للجدولة'],
        );

        $level = Level::firstOrCreate(['name' => 'فرقة تجريبية للجدولة']);

        return Course::create([
            'code' => 'SCH-'.Str::random(6),
            'name' => 'مادة تجريبية للجدولة',
            'hours' => 3,
            'is_selected' => false,
            'is_active' => true,
            'department_id' => $department->id,
            'level_id' => $level->id,
            'semester' => 'الأول',
        ]);
    }
}
