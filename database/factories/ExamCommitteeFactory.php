<?php

namespace Database\Factories;

use App\Enums\ExamSessionStatus;
use App\Enums\Semester;
use App\Models\ExamCommittee;
use App\Models\Venue;
use App\Models\Year;
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
            'year_id' => fn () => Year::firstOrCreate(
                ['year' => '2025-2026'],
                ['first_semester_status' => 'open_registration'],
            )->id,
            'semester' => Semester::FIRST,
            'venue_id' => fn () => Venue::factory()->create()->id,
            'name' => 'لجنة '.fake()->unique()->numberBetween(1, 9999),
            'capacity' => 60,
            'status' => ExamSessionStatus::DRAFT,
            'notes' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => ExamSessionStatus::PUBLISHED]);
    }
}
