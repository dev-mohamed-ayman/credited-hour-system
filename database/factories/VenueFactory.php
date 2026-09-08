<?php

namespace Database\Factories;

use App\Enums\VenueType;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'مدرج '.fake()->unique()->numerify('###'),
            'type' => VenueType::AUDITORIUM,
            'capacity' => 300,
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function auditorium(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => VenueType::AUDITORIUM,
            'capacity' => 300,
        ]);
    }

    public function lab(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'معمل '.fake()->unique()->numerify('###'),
            'type' => VenueType::LAB,
            'capacity' => 40,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
