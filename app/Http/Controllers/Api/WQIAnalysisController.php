<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaterSample;
use App\Services\WQICalculatorService;
use App\Services\AIAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class WQIAnalysisController extends Controller
{
    protected $wqiCalculator;
    protected $aiAnalysis;

    public function __construct(
        WQICalculatorService $wqiCalculator,
        AIAnalysisService $aiAnalysis
    ) {
        $this->wqiCalculator = $wqiCalculator;
        $this->aiAnalysis = $aiAnalysis;
    }

    /**
     * Calculate WQI for custom parameters (without saving)
     * POST /api/analysis/calculate
     */
    public function calculate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ph' => 'nullable|numeric|between:0,14',
            'temperature' => 'nullable|numeric',
            'turbidity' => 'nullable|numeric|min:0',
            'tds' => 'nullable|numeric|min:0',
            'dissolved_oxygen' => 'nullable|numeric|min:0',
            'bod' => 'nullable|numeric|min:0',
            'nitrate' => 'nullable|numeric|min:0',
            'fecal_coliform' => 'nullable|integer|min:0',
            // Add other parameters as needed
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Create temporary sample object for calculation
        $tempSample = new WaterSample($request->all());

        // Calculate all WQI standards
        $results = $this->wqiCalculator->calculateAllWQI($tempSample);

        // Get detailed analysis
        $analysis = [
            'wqi_results' => [
                'who' => [
                    'value' => $results['wqi_who'],
                    'status' => $this->getWQIStatus($results['wqi_who']),
                    'description' => 'World Health Organization Standard',
                ],
                'nsf' => [
                    'value' => $results['wqi_nsf'],
                    'status' => $this->getWQIStatus($results['wqi_nsf']),
                    'description' => 'National Sanitation Foundation (USA)',
                ],
                'ccme' => [
                    'value' => $results['wqi_ccme'],
                    'status' => $this->getWQIStatus($results['wqi_ccme']),
                    'description' => 'Canadian Council of Ministers',
                ],
                'egyptian_custom' => [
                    'value' => $results['wqi_custom'],
                    'status' => $this->getWQIStatus($results['wqi_custom']),
                    'description' => 'Egyptian Water Quality Standard',
                ],
            ],
            'quality_status' => $results['quality_status'],
            'risk_level' => $results['risk_level'],
            'risk_factors' => $results['risk_factors'],
            'recommendations' => $this->generateRecommendations($results),
            'compliance' => $this->checkCompliance($tempSample),
        ];

        return response()->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    /**
     * Compare WQI standards side-by-side
     * POST /api/analysis/compare
     */
    public function compareStandards(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sample_id' => 'required|exists:water_samples,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $sample = WaterSample::with('location')->findOrFail($request->sample_id);

        $comparison = [
            'sample_info' => [
                'code' => $sample->sample_code,
                'location' => $sample->location->name,
                'collection_date' => $sample->collection_date,
            ],
            'standards_comparison' => [
                'who' => [
                    'wqi' => $sample->wqi_who,
                    'status' => $this->getWQIStatus($sample->wqi_who),
                    'grade' => $this->getGrade($sample->wqi_who),
                    'acceptable' => $sample->wqi_who >= 50,
                ],
                'nsf' => [
                    'wqi' => $sample->wqi_nsf,
                    'status' => $this->getWQIStatus($sample->wqi_nsf),
                    'grade' => $this->getGrade($sample->wqi_nsf),
                    'acceptable' => $sample->wqi_nsf >= 50,
                ],
                'ccme' => [
                    'wqi' => $sample->wqi_ccme,
                    'status' => $this->getWQIStatus($sample->wqi_ccme),
                    'grade' => $this->getGrade($sample->wqi_ccme),
                    'acceptable' => $sample->wqi_ccme >= 50,
                ],
                'egyptian' => [
                    'wqi' => $sample->wqi_custom,
                    'status' => $this->getWQIStatus($sample->wqi_custom),
                    'grade' => $this->getGrade($sample->wqi_custom),
                    'acceptable' => $sample->wqi_custom >= 50,
                ],
            ],
            'parameter_analysis' => $this->analyzeParameters($sample),
            'visual_data' => [
                'radar_chart' => $this->getRadarChartData($sample),
                'comparison_chart' => $this->getComparisonChartData($sample),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $comparison,
        ]);
    }

    /**
     * Get all available standards and their configurations
     * GET /api/analysis/standards
     */
    public function getStandards(): JsonResponse
    {
        $standards = [
            'who' => [
                'name' => 'World Health Organization',
                'code' => 'WHO',
                'description' => 'International standard for drinking water quality',
                'parameters' => [
                    'ph' => ['min' => 6.5, 'max' => 8.5],
                    'tds' => ['max' => 500],
                    'turbidity' => ['max' => 5],
                    'nitrate' => ['max' => 50],
                    'fecal_coliform' => ['max' => 0],
                ],
                'water_types' => ['drinking'],
            ],
            'nsf' => [
                'name' => 'National Sanitation Foundation',
                'code' => 'NSF-WQI',
                'description' => 'US standard for general water quality assessment',
                'parameters' => [
                    'dissolved_oxygen' => ['weight' => 0.17],
                    'fecal_coliform' => ['weight' => 0.16],
                    'ph' => ['weight' => 0.11],
                    'bod' => ['weight' => 0.11],
                    'temperature' => ['weight' => 0.10],
                    'phosphate' => ['weight' => 0.10],
                    'nitrate' => ['weight' => 0.10],
                    'turbidity' => ['weight' => 0.08],
                    'tds' => ['weight' => 0.07],
                ],
                'water_types' => ['surface_water', 'recreational'],
            ],
            'ccme' => [
                'name' => 'Canadian Council of Ministers',
                'code' => 'CCME-WQI',
                'description' => 'Canadian standard with emphasis on ecosystem health',
                'calculation_method' => 'F1 (scope) + F2 (frequency) + F3 (amplitude)',
                'water_types' => ['drinking', 'aquatic_life'],
            ],
            'egyptian' => [
                'name' => 'Egyptian Custom Standard',
                'code' => 'EGY-WQI',
                'description' => 'Adapted for Egyptian environmental conditions',
                'priority_parameters' => [
                    'fecal_coliform' => 10,
                    'e_coli' => 10,
                    'lead' => 9,
                    'arsenic' => 9,
                    'nitrate' => 8,
                    'ph' => 7,
                    'dissolved_oxygen' => 7,
                ],
                'water_types' => ['drinking', 'irrigation'],
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $standards,
        ]);
    }

    /**
     * Predict future water quality using AI
     * POST /api/analysis/predict
     */
    public function predictFuture(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location_id' => 'required|exists:monitoring_locations,id',
            'days_ahead' => 'nullable|integer|min:1|max:90',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $locationId = $request->location_id;
        $daysAhead = $request->get('days_ahead', 30);

        // Get historical data
        $historicalSamples = WaterSample::where('location_id', $locationId)
            ->orderBy('collection_date')
            ->limit(100)
            ->get();

        if ($historicalSamples->count() < 5) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient historical data for prediction (minimum 5 samples required)',
            ], 400);
        }

        // Call AI prediction service (Huawei ModelArts)
        $prediction = $this->aiAnalysis->predictFutureQuality(
            $historicalSamples,
            $daysAhead
        );

        return response()->json([
            'success' => true,
            'data' => [
                'location_id' => $locationId,
                'prediction_date' => now()->addDays($daysAhead)->format('Y-m-d'),
                'predicted_wqi' => $prediction['wqi'],
                'confidence' => $prediction['confidence'],
                'trend' => $prediction['trend'],
                'risk_assessment' => $prediction['risk_assessment'],
                'recommendations' => $prediction['recommendations'],
                'timeline' => $prediction['timeline'], // Day-by-day predictions
            ],
        ]);
    }

    // Helper methods

    private function getWQIStatus($wqi): string
    {
        if ($wqi === null)
            return 'unknown';
        if ($wqi >= 90)
            return 'excellent';
        if ($wqi >= 70)
            return 'good';
        if ($wqi >= 50)
            return 'fair';
        if ($wqi >= 25)
            return 'poor';
        return 'very_poor';
    }

    private function getGrade($wqi): string
    {
        if ($wqi === null)
            return 'N/A';
        if ($wqi >= 90)
            return 'A';
        if ($wqi >= 80)
            return 'B';
        if ($wqi >= 70)
            return 'C';
        if ($wqi >= 60)
            return 'D';
        return 'F';
    }

    private function generateRecommendations($results): array
    {
        $recommendations = [];

        if ($results['risk_level'] === 'critical') {
            $recommendations[] = 'URGENT: Water is unsafe for consumption. Immediate action required.';
        }

        foreach ($results['risk_factors'] as $risk) {
            switch ($risk['parameter']) {
                case 'fecal_coliform':
                    $recommendations[] = 'Bacterial contamination detected. Boil water before use or use chlorination.';
                    break;
                case 'lead':
                    $recommendations[] = 'High lead levels. Check plumbing systems and avoid using for drinking.';
                    break;
                case 'nitrate':
                    $recommendations[] = 'High nitrate levels. Not safe for infants. Consider alternative water source.';
                    break;
                case 'turbidity':
                    $recommendations[] = 'High turbidity. Use filtration system or let water settle before use.';
                    break;
            }
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Water quality is within acceptable limits. Regular monitoring recommended.';
        }

        return $recommendations;
    }

    private function checkCompliance($sample): array
    {
        $compliance = [
            'who' => ['compliant' => true, 'violations' => []],
            'nsf' => ['compliant' => true, 'violations' => []],
            'egyptian' => ['compliant' => true, 'violations' => []],
        ];

        // WHO Standards
        if ($sample->ph < 6.5 || $sample->ph > 8.5) {
            $compliance['who']['compliant'] = false;
            $compliance['who']['violations'][] = 'pH out of range';
        }

        if ($sample->fecal_coliform > 0) {
            $compliance['who']['compliant'] = false;
            $compliance['who']['violations'][] = 'Bacterial contamination';
        }

        // Add more checks...

        return $compliance;
    }

    private function analyzeParameters($sample): array
    {
        return [
            'physical' => [
                'temperature' => ['value' => $sample->temperature, 'status' => 'normal'],
                'turbidity' => ['value' => $sample->turbidity, 'status' => $this->getParameterStatus($sample->turbidity, 5)],
            ],
            'chemical' => [
                'ph' => ['value' => $sample->ph, 'status' => $this->getPHStatus($sample->ph)],
                'tds' => ['value' => $sample->tds, 'status' => $this->getParameterStatus($sample->tds, 500)],
            ],
            'biological' => [
                'fecal_coliform' => ['value' => $sample->fecal_coliform, 'status' => $sample->fecal_coliform > 0 ? 'critical' : 'safe'],
            ],
        ];
    }

    private function getParameterStatus($value, $threshold): string
    {
        if ($value === null)
            return 'not_tested';
        if ($value <= $threshold)
            return 'acceptable';
        if ($value <= $threshold * 1.5)
            return 'warning';
        return 'exceeded';
    }

    private function getPHStatus($ph): string
    {
        if ($ph === null)
            return 'not_tested';
        if ($ph >= 6.5 && $ph <= 8.5)
            return 'acceptable';
        if ($ph >= 6.0 && $ph <= 9.0)
            return 'warning';
        return 'exceeded';
    }

    private function getRadarChartData($sample): array
    {
        return [
            'labels' => ['pH', 'TDS', 'Turbidity', 'DO', 'Nitrate', 'Bacteria'],
            'datasets' => [
                [
                    'label' => 'Current Sample',
                    'data' => [
                        $this->normalizeValue($sample->ph, 14) * 100,
                        $this->normalizeValue($sample->tds, 1000) * 100,
                        $this->normalizeValue($sample->turbidity, 10) * 100,
                        $this->normalizeValue($sample->dissolved_oxygen, 15) * 100,
                        $this->normalizeValue($sample->nitrate, 100) * 100,
                        $sample->fecal_coliform > 0 ? 0 : 100,
                    ],
                ],
            ],
        ];
    }

    private function getComparisonChartData($sample): array
    {
        return [
            'labels' => ['WHO', 'NSF', 'CCME', 'Egyptian'],
            'datasets' => [
                [
                    'label' => 'WQI Scores',
                    'data' => [
                        $sample->wqi_who,
                        $sample->wqi_nsf,
                        $sample->wqi_ccme,
                        $sample->wqi_custom,
                    ],
                ],
            ],
        ];
    }

    private function normalizeValue($value, $max): float
    {
        if ($value === null)
            return 0;
        return min($value / $max, 1);
    }
}
