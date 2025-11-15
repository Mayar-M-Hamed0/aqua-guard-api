<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\MonitoringLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

class WaterSampleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sample_code' => 'WS-' . now()->year . '-' . $this->faker->unique()->numerify('###'),
            'location_id' => MonitoringLocation::factory(),
            'collected_by' => User::factory(),
            'collection_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'collection_time' => $this->faker->time(),

            'temperature' => $this->faker->randomFloat(2, 10, 35),
            'turbidity' => $this->faker->randomFloat(2, 0, 50),
            'color' => $this->faker->safeColorName(),
            'odor_threshold' => $this->faker->randomFloat(2, 0, 5),

            'ph' => $this->faker->randomFloat(2, 6.0, 9.0),
            'electrical_conductivity' => $this->faker->randomFloat(2, 200, 2000),
            'tds' => $this->faker->randomFloat(2, 100, 1500),
            'tss' => $this->faker->randomFloat(2, 1, 200),

            'total_hardness' => $this->faker->randomFloat(2, 50, 500),
            'calcium' => $this->faker->randomFloat(2, 5, 100),
            'magnesium' => $this->faker->randomFloat(2, 1, 60),
            'sodium' => $this->faker->randomFloat(2, 10, 200),
            'potassium' => $this->faker->randomFloat(2, 1, 20),
            'chloride' => $this->faker->randomFloat(2, 10, 250),
            'sulfate' => $this->faker->randomFloat(2, 10, 300),
            'alkalinity' => $this->faker->randomFloat(2, 10, 300),

            'dissolved_oxygen' => $this->faker->randomFloat(2, 1, 12),
            'bod' => $this->faker->randomFloat(2, 1, 30),
            'cod' => $this->faker->randomFloat(2, 5, 80),

            'nitrate' => $this->faker->randomFloat(2, 0.1, 50),
            'nitrite' => $this->faker->randomFloat(2, 0.01, 5),
            'ammonia' => $this->faker->randomFloat(2, 0.01, 10),
            'total_nitrogen' => $this->faker->randomFloat(2, 0.5, 40),
            'phosphate' => $this->faker->randomFloat(2, 0.01, 5),
            'total_phosphorus' => $this->faker->randomFloat(2, 0.1, 10),

            // heavy metals realistic trace values
            'lead' => $this->faker->randomFloat(4, 0.0001, 0.05),
            'mercury' => $this->faker->randomFloat(4, 0.00001, 0.01),
            'arsenic' => $this->faker->randomFloat(4, 0.0001, 0.05),
            'cadmium' => $this->faker->randomFloat(4, 0.00001, 0.01),
            'chromium' => $this->faker->randomFloat(4, 0.0001, 0.1),
            'copper' => $this->faker->randomFloat(4, 0.0001, 1),
            'iron' => $this->faker->randomFloat(4, 0.001, 3),
            'manganese' => $this->faker->randomFloat(4, 0.0005, 1),
            'zinc' => $this->faker->randomFloat(4, 0.001, 5),

            'total_coliform' => $this->faker->numberBetween(0, 500),
            'fecal_coliform' => $this->faker->numberBetween(0, 200),
            'e_coli' => $this->faker->numberBetween(0, 100),

            'wqi_who' => $this->faker->randomFloat(2, 20, 95),
            'wqi_nsf' => $this->faker->randomFloat(2, 20, 95),
            'wqi_ccme' => $this->faker->randomFloat(2, 20, 95),
            'wqi_custom' => $this->faker->randomFloat(2, 20, 95),

            'quality_status' => $this->faker->randomElement(['excellent','good','fair','poor','very_poor']),

            'ai_predictions' => ['risk' => $this->faker->randomElement(['low','medium','high'])],
            'ai_confidence' => $this->faker->randomFloat(2, 60, 99),
            'ai_recommendations' => $this->faker->sentence(),

            'risk_level' => $this->faker->randomElement(['low','medium','high','critical']),
            'risk_factors' => ['factor1' => $this->faker->word()],

            'lab_name' => $this->faker->company(),
            'lab_certificate' => 'CERT-' . $this->faker->numerify('###'),
            'notes' => $this->faker->sentence(),
            'attachments' => [],

            'status' => $this->faker->randomElement([
                'pending_analysis','analyzed','verified','flagged','archived'
            ]),

            'verified_by' => User::factory(),
            'verified_at' => now(),
        ];
    }
}
