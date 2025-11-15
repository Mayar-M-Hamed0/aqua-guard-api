<?php

namespace Database\Factories;

use App\Models\MonitoringLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

class WqiTrendFactory extends Factory
{
    public function definition(): array
    {
        return [
            'location_id' => MonitoringLocation::factory(),
            'date' => $this->faker->date(),
            'avg_wqi' => $this->faker->randomFloat(2, 20, 95),
            'min_wqi' => $this->faker->randomFloat(2, 10, 80),
            'max_wqi' => $this->faker->randomFloat(2, 30, 100),
            'sample_count' => $this->faker->numberBetween(5, 30),
            'trend_direction' => $this->faker->randomElement(['improving','stable','declining']),
            'parameter_averages' => ['ph' => 7.3],
        ];
    }
}
