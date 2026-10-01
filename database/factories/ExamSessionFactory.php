<?php

namespace Database\Factories;

use App\Enums\ExamType;
use App\Models\Course;
use App\Models\Department;
use App\Models\ExamCommittee;
use App\Models\ExamSession;
use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExamSession>
 */
class ExamSessionFactory extends Factory
{
    protected $model = ExamSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_committee_id' => ExamCommittee::factory(),
            'course_id' => fn () => $this->resolveCourse()->id,
            'type' => ExamType::REGULAR,
            'exam_date' => '2026-01-15',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'notes' => null,
        ];
    }

    public function resit(): static
    {
        return $this->state(fn () => ['type' => ExamType::RESIT]);
    }

    public function improvement(): static
    {
        return $this->state(fn () => ['type' => ExamType::IMPROVEMENT]);
    }

    private function resolveCourse(): Course
    {
        $department = Department::firstOrCreate(
            ['code' => 'EXAM-DEPT'],
            ['name' => 'تخصص تجريبي للامتحانات'],
        );

        $level = Level::firstOrCreate(['name' => 'فرقة تجريبية للامتحانات']);

        return Course::create([
            'code' => 'EX-'.Str::random(6),
            'name' => 'مادة تجريبية للامتحانات',
            'hours' => 3,
            'is_selected' => false,
            'is_active' => true,
            'department_id' => $department->id,
            'level_id' => $level->id,
            'semester' => 'الأول',
        ]);
    }
}
