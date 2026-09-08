<?php

namespace Database\Factories;

use App\Models\ExamCommittee;
use App\Models\ExamSession;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamCommittee>
 */
class ExamCommitteeFactory extends Factory
{
    protected $model = ExamCommittee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_session_id' => ExamSession::factory(),
            'venue_id' => fn () => Venue::factory()->create()->id,
            'name' => 'لجنة '.fake()->unique()->numberBetween(1, 9999),
            'capacity' => 60,
        ];
    }
}
