<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalysisReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_code' => 'REP-' . $this->faker->unique()->numerify('####'),
            'report_type' => $this->faker->randomElement([
                'single_sample','location_trend','comparative','regional','custom'
            ]),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'start_date' => now()->subDays(30),
            'end_date' => now(),
            'location_ids' => [1, 2],
            'parameter_filters' => ['ph', 'tds'],
            'summary_statistics' => ['avg_ph' => 7.5],
            'trends_analysis' => ['trend' => 'improving'],
            'charts_data' => ['chart1' => []],
            'conclusions' => $this->faker->paragraph(),
            'recommendations' => $this->faker->paragraph(),
            'pdf_path' => null,
            'excel_path' => null,
            'generated_by' => User::factory(),
            'status' => 'completed',
        ];
    }
}
