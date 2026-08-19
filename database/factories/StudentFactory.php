<?php

namespace Database\Factories;

use App\Enums\Student\StudentStatus;
use App\Models\CertificateType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'certificate_type_id' => CertificateType::factory(),
            'national_id' => fake()->unique()->numerify('##############'),
            'gender' => fake()->randomElement(['male', 'female']),
            'status' => StudentStatus::REGISTERED,
            'username' => fake()->unique()->numerify('2#######'),
            'password' => 'Password@1',
            'plain_password' => 'Password@1',
        ];
    }
}
