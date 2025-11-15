<?php

namespace Database\Factories;

use App\Models\WqiStandard;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParameterStandardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'standard_id' => WqiStandard::factory(),
            'parameter_name' => $this->faker->randomElement(['ph','tds','nitrate','lead']),
            'ideal_value' => $this->faker->randomFloat(4, 0, 10),
            'min_acceptable' => 0,
            'max_acceptable' => $this->faker->randomFloat(4, 5, 500),
            'min_permissible' => 0,
            'max_permissible' => $this->faker->randomFloat(4, 10, 1000),
            'unit' => $this->faker->randomElement(['mg/L', 'NTU', 'µg/L']),
            'weight' => $this->faker->numberBetween(1, 5),
            'health_impact' => $this->faker->sentence(),
        ];
    }
}
