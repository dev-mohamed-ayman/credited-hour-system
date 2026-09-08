<?php

namespace Database\Factories;

use App\Models\ExamCommittee;
use App\Models\ExamSeatAssignment;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSeatAssignment>
 */
class ExamSeatAssignmentFactory extends Factory
{
    protected $model = ExamSeatAssignment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $session = ExamSession::factory();

        return [
            'exam_session_id' => $session,
            'exam_committee_id' => ExamCommittee::factory()->merge(['exam_session_id' => $session]),
            'student_id' => Student::factory(),
            'seat_number' => (string) fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
