<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonitoringLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Location ' . $this->faker->unique()->word(),
            'code' => 'LOC-' . $this->faker->unique()->numerify('###'),
            'description' => $this->faker->sentence(),
            'latitude' => $this->faker->latitude(22, 32),
            'longitude' => $this->faker->longitude(25, 35),
            'address' => $this->faker->address(),
            'type' => $this->faker->randomElement([
                'river','lake','groundwater','sea','reservoir',
                'treatment_plant','distribution_network'
            ]),
            'governorate' => $this->faker->city(),
            'city' => $this->faker->city(),
            'is_active' => true,
            'metadata' => [
                'depth_m' => $this->faker->randomFloat(2, 1, 50),
                'notes' => $this->faker->sentence()
            ],
            'created_by' => User::inRandomOrder()->value('id') ?? User::factory(),
        ];
    }
}
