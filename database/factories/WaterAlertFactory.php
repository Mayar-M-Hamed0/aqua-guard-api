<?php

namespace Database\Factories;

use App\Models\WaterSample;
use App\Models\MonitoringLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WaterAlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sample_id' => WaterSample::factory(),
            'location_id' => MonitoringLocation::factory(),
            'severity' => $this->faker->randomElement(['info','warning','critical','emergency']),
            'alert_type' => $this->faker->randomElement(['parameter_exceeded','high_contamination']),
            'parameter_name' => $this->faker->randomElement(['ph','lead','nitrate']),
            'parameter_value' => $this->faker->randomFloat(4, 0, 20),
            'threshold_value' => $this->faker->randomFloat(4, 0, 10),
            'message' => $this->faker->sentence(),
            'affected_parameters' => ['ph','lead'],
            'is_read' => false,
            'is_resolved' => false,
            'resolved_by' => User::factory(),
            'resolved_at' => null,
            'resolution_notes' => null,
        ];
    }
}
