<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WqiStandardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['WHO','NSF','CCME','Egyptian']),
            'code' => strtoupper($this->faker->unique()->lexify('STD-???')),
            'description' => $this->faker->sentence(),
            'water_type' => $this->faker->randomElement(['drinking','irrigation','industrial','recreational']),
            'parameters_config' => ['ph', 'tds', 'nitrate'],
            'calculation_method' => ['weights' => ['ph' => 3, 'tds' => 2]],
            'is_active' => true,
        ];
    }
}
