<?php

namespace Database\Factories;

use App\Models\ExamCommittee;
use App\Models\ExamCommitteeStudent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamCommitteeStudent>
 */
class ExamCommitteeStudentFactory extends Factory
{
    protected $model = ExamCommitteeStudent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_committee_id' => ExamCommittee::factory(),
            'seat_number' => fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
