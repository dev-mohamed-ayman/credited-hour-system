<?php

namespace Database\Factories;

use App\Enums\Semester;
use App\Models\TermClosure;
use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TermClosure>
 */
class TermClosureFactory extends Factory
{
    protected $model = TermClosure::class;

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
            'closed_by' => null,
        ];
    }
}
