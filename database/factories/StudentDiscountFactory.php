<?php

namespace Database\Factories;

use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Enums\DiscountStatus;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentDiscount>
 */
class StudentDiscountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomElement([500, 300, 1000]);

        return [
            'student_id' => Student::factory(),
            'scope' => DiscountScope::Registration,
            'fee_id' => null,
            'year_id' => null,
            'semester' => null,
            'mode' => DiscountMode::Fixed,
            'value' => $amount,
            'remaining_amount' => $amount,
            'status' => DiscountStatus::Active,
            'reason' => fake()->sentence(3),
            'decision_number' => 'ق'.fake()->unique()->numerify('####'),
            'created_by' => User::factory(),
        ];
    }

    public function fixed(float $amount = 500): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => DiscountMode::Fixed,
            'value' => $amount,
            'remaining_amount' => $amount,
        ]);
    }

    public function percentage(float $percent = 50): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => DiscountMode::Percentage,
            'value' => $percent,
            'remaining_amount' => null,
        ]);
    }

    public function scoped(DiscountScope $scope, ?int $feeId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => $scope,
            'fee_id' => $feeId,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DiscountStatus::Revoked,
            'revoked_reason' => fake()->sentence(3),
            'revoked_at' => now(),
        ]);
    }
}
