<?php

namespace App\Services;

use App\Models\WaterSample;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIAnalysisService
{
    protected $modelArtsEndpoint;
    protected $apiKey;

    public function __construct()
    {
        $this->modelArtsEndpoint = config('services.huawei.modelarts_endpoint');
        $this->apiKey = config('services.huawei.modelarts_token');
    }

    /**
     * Analyze a water sample using AI
     */
    public function analyze(WaterSample $sample): array
    {
        try {
            // Prepare input data
            $inputData = $this->prepareInputData($sample);

            // Call Huawei ModelArts API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->modelArtsEndpoint . '/predict', [
                'data' => $inputData,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return $this->processAIResponse($result);
            }

            // Fallback to rule-based analysis if AI fails
            return $this->ruleBasedAnalysis($sample);

        } catch (\Exception $e) {
            Log::error('AI Analysis failed: ' . $e->getMessage());
            return $this->ruleBasedAnalysis($sample);
        }
    }

    /**
     * Predict future water quality
     */
    public function predictFutureQuality(Collection $historicalSamples, int $daysAhead): array
    {
        try {
            // Prepare time series data
            $timeSeriesData = $historicalSamples->map(function ($sample) {
                return [
                    'date' => $sample->collection_date->format('Y-m-d'),
                    'wqi' => $sample->wqi_custom,
                    'parameters' => [
                        'ph' => $sample->ph,
                        'tds' => $sample->tds,
                        'turbidity' => $sample->turbidity,
                        'dissolved_oxygen' => $sample->dissolved_oxygen,
                    ],
                ];
            })->toArray();

            // Call prediction API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->modelArtsEndpoint . '/forecast', [
                'time_series' => $timeSeriesData,
                'forecast_days' => $daysAhead,
            ]);

            if ($response->successful()) {
                return $this->processPredictionResponse($response->json(), $daysAhead);
            }

            // Fallback to statistical prediction
            return $this->statisticalPrediction($historicalSamples, $daysAhead);

        } catch (\Exception $e) {
            Log::error('Prediction failed: ' . $e->getMessage());
            return $this->statisticalPrediction($historicalSamples, $daysAhead);
        }
    }

    /**
     * Prepare input data for AI model
     */
    private function prepareInputData(WaterSample $sample): array
    {
        return [
            'features' => [
                'ph' => $sample->ph ?? 0,
                'tds' => $sample->tds ?? 0,
                'turbidity' => $sample->turbidity ?? 0,
                'dissolved_oxygen' => $sample->dissolved_oxygen ?? 0,
                'temperature' => $sample->temperature ?? 0,
                'nitrate' => $sample->nitrate ?? 0,
                'bod' => $sample->bod ?? 0,
                'fecal_coliform' => $sample->fecal_coliform ?? 0,
            ],
            'location_type' => $sample->location->type,
            'season' => $this->getSeason($sample->collection_date),
        ];
    }

    /**
     * Process AI response
     */
    private function processAIResponse(array $result): array
    {
        return [
            'predictions' => $result['predictions'] ?? [],
            'confidence' => $result['confidence'] ?? 0,
            'recommendations' => $result['recommendations'] ?? $this->generateDefaultRecommendations(),
        ];
    }

    /**
     * Rule-based analysis fallback
     */
    private function ruleBasedAnalysis(WaterSample $sample): array
    {
        $recommendations = [];
        $predictions = [
            'quality_trend' => 'stable',
            'contamination_risk' => 'low',
        ];

        // Check critical parameters
        if ($sample->fecal_coliform > 0) {
            $recommendations[] = 'Bacterial contamination detected. Water requires disinfection treatment.';
            $predictions['contamination_risk'] = 'high';
        }

        if ($sample->lead > 0.01) {
            $recommendations[] = 'Lead concentration exceeds safe limits. Check water source and treatment.';
            $predictions['contamination_risk'] = 'high';
        }

        if ($sample->nitrate > 50) {
            $recommendations[] = 'High nitrate levels detected. Not suitable for infant consumption.';
            $predictions['contamination_risk'] = 'medium';
        }

        if ($sample->dissolved_oxygen < 5) {
            $recommendations[] = 'Low dissolved oxygen indicates possible organic pollution.';
        }

        if ($sample->turbidity > 5) {
            $recommendations[] = 'High turbidity. Filtration and settling recommended before use.';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Water quality parameters are within acceptable limits.';
            $recommendations[] = 'Continue regular monitoring to maintain quality standards.';
        }

        return [
            'predictions' => $predictions,
            'confidence' => 75.0,
            'recommendations' => implode(' ', $recommendations),
        ];
    }

    /**
     * Process prediction response
     */
    private function processPredictionResponse(array $result, int $daysAhead): array
    {
        return [
            'wqi' => $result['forecast']['wqi'] ?? 0,
            'confidence' => $result['confidence'] ?? 0,
            'trend' => $result['trend'] ?? 'stable',
            'risk_assessment' => $result['risk_level'] ?? 'medium',
            'recommendations' => $result['recommendations'] ?? [],
            'timeline' => $result['timeline'] ?? [],
            'prediction_date' => now()->addDays($daysAhead)->format('Y-m-d'),
        ];
    }

    /**
     * Statistical prediction fallback
     */
    private function statisticalPrediction(Collection $samples, int $daysAhead): array
    {
        if ($samples->count() < 5) {
            return [
                'wqi' => null,
                'confidence' => 0,
                'trend' => 'insufficient_data',
                'risk_assessment' => 'unknown',
                'recommendations' => ['Insufficient historical data for prediction. More samples required.'],
                'timeline' => [],
                'prediction_date' => now()->addDays($daysAhead)->format('Y-m-d'),
            ];
        }

        // Calculate trend using linear regression
        $wqiValues = $samples->pluck('wqi_custom')->toArray();
        $n = count($wqiValues);
        $x = range(1, $n);

        $sumX = array_sum($x);
        $sumY = array_sum($wqiValues);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $wqiValues[$i];
            $sumX2 += $x[$i] * $x[$i];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Predict future WQI
        $predictedWQI = $intercept + $slope * ($n + $daysAhead);
        $predictedWQI = max(0, min(100, $predictedWQI)); // Clamp between 0-100

        // Determine trend
        $trend = 'stable';
        if ($slope > 0.5) $trend = 'improving';
        if ($slope < -0.5) $trend = 'declining';

        // Risk assessment
        $riskAssessment = 'low';
        if ($predictedWQI < 50) $riskAssessment = 'high';
        elseif ($predictedWQI < 70) $riskAssessment = 'medium';

        // Generate timeline
        $timeline = [];
        for ($day = 1; $day <= min($daysAhead, 30); $day++) {
            $wqi = $intercept + $slope * ($n + $day);
            $timeline[] = [
                'date' => now()->addDays($day)->format('Y-m-d'),
                'wqi' => round(max(0, min(100, $wqi)), 2),
            ];
        }

        // Recommendations
        $recommendations = [];
        if ($trend === 'declining') {
            $recommendations[] = 'Water quality is declining. Investigate pollution sources immediately.';
            $recommendations[] = 'Increase monitoring frequency to weekly intervals.';
        } elseif ($trend === 'improving') {
            $recommendations[] = 'Water quality is improving. Continue current treatment protocols.';
        } else {
            $recommendations[] = 'Water quality is stable. Maintain regular monitoring schedule.';
        }

        return [
            'wqi' => round($predictedWQI, 2),
            'confidence' => 65.0,
            'trend' => $trend,
            'risk_assessment' => $riskAssessment,
            'recommendations' => $recommendations,
            'timeline' => $timeline,
            'prediction_date' => now()->addDays($daysAhead)->format('Y-m-d'),
        ];
    }

    /**
     * Get season from date
     */
    private function getSeason($date): string
    {
        $month = $date->month;

        if (in_array($month, [12, 1, 2])) return 'winter';
        if (in_array($month, [3, 4, 5])) return 'spring';
        if (in_array($month, [6, 7, 8])) return 'summer';
        return 'autumn';
    }

    /**
     * Generate default recommendations
     */
    private function generateDefaultRecommendations(): string
    {
        return 'Continue regular water quality monitoring. Ensure proper treatment protocols are followed.';
    }
}
