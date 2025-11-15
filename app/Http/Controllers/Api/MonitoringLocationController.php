<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonitoringLocation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class MonitoringLocationController extends Controller
{
    /**
     * Get all monitoring locations
     * GET /api/locations
     */
    public function index(Request $request): JsonResponse
    {
        $query = MonitoringLocation::with(['creator']);

        // Filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('governorate')) {
            $query->where('governorate', $request->governorate);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        // Nearby locations
        if ($request->has('lat') && $request->has('lng')) {
            $radius = $request->get('radius', 10); // km
            $query->nearby($request->lat, $request->lng, $radius);
        }

        $locations = $query->get();

        // Add statistics for each location
        $locations->each(function ($location) {
            $location->total_samples = $location->samples()->count();
            $location->latest_sample = $location->latestSample;
            $location->average_wqi = $location->averageWqi;
            $location->current_risk_level = $location->currentRiskLevel;
        });

        return response()->json([
            'success' => true,
            'data' => $locations,
        ]);
    }

    /**
     * Get single location with detailed info
     * GET /api/locations/{id}
     */
    public function show($id): JsonResponse
    {
        $location = MonitoringLocation::with([
            'creator',
            'samples' => function ($q) {
                $q->latest('collection_date')->limit(10);
            }
        ])->findOrFail($id);
        $samples = $location->samples()->get();
        // Statistics
        $stats = [
            'total_samples' => $location->samples()->count(),
            'latest_sample' => $location->latestSample,
            'average_wqi' => $location->averageWqi,
            'current_risk_level' => $location->currentRiskLevel,
            'quality_distribution' => $location->samples()
                ->selectRaw('quality_status, COUNT(*) as count')
                ->groupBy('quality_status')
                ->pluck('count', 'quality_status'),
            'trend_last_30_days' => $this->calculateTrend($location, 30),
            'parameter_averages' => [
                'ph' => round($samples->avg('ph'), 2),
                'tds' => round($samples->avg('tds'), 2),
                'turbidity' => round($samples->avg('turbidity'), 2),
                'dissolved_oxygen' => round($samples->avg('dissolved_oxygen'), 2),
                'nitrate' => round($samples->avg('nitrate'), 2),
                'fecal_coliform' => round($samples->avg('fecal_coliform'), 2),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'location' => $location,
                'statistics' => $stats,
            ],
        ]);
    }

    /**
     * Create new monitoring location
     * POST /api/locations
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string',
            'type' => 'required|in:river,lake,groundwater,sea,reservoir,treatment_plant,distribution_network',
            'governorate' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $locationCode = MonitoringLocation::generateLocationCode();

        $location = MonitoringLocation::create([
            'name' => $request->name,
            'code' => $locationCode,
            'description' => $request->description,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'type' => $request->type,
            'governorate' => $request->governorate,
            'city' => $request->city,
            'metadata' => $request->metadata,
            'created_by' => auth()->id(),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Location created successfully',
            'data' => $location->load('creator'),
        ], 201);
    }

    /**
     * Update location
     * PUT /api/locations/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $location = MonitoringLocation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'address' => 'nullable|string',
            'type' => 'sometimes|in:river,lake,groundwater,sea,reservoir,treatment_plant,distribution_network',
            'governorate' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $location->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully',
            'data' => $location->fresh(),
        ]);
    }

    /**
     * Delete location (soft delete)
     * DELETE /api/locations/{id}
     */
    public function destroy($id): JsonResponse
    {
        $location = MonitoringLocation::findOrFail($id);

        // Check if location has samples
        if ($location->samples()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete location with existing samples. Archive it instead.',
            ], 400);
        }

        $location->delete();

        return response()->json([
            'success' => true,
            'message' => 'Location deleted successfully',
        ]);
    }

    /**
     * Get locations for map view
     * GET /api/locations/map
     */
    public function mapView(Request $request): JsonResponse
    {
        $locations = MonitoringLocation::active()
            ->with([
                'samples' => function ($q) {
                    $q->latest('collection_date')->limit(1);
                }
            ])
            ->get();

        $mapData = $locations->map(function ($location) {
            return [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'type' => $location->type,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'latest_wqi' => $location->latestSample?->wqi_custom,
                'quality_status' => $location->latestSample?->quality_status,
                'risk_level' => $location->latestSample?->risk_level,
                'last_sampled' => $location->latestSample?->collection_date,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $mapData,
        ]);
    }

    /**
     * Get location statistics
     * GET /api/locations/{id}/statistics
     */
    /**
     * Get location statistics
     * GET /api/locations/{id}/statistics
     */
    public function statistics($id, Request $request): JsonResponse
    {
        $location = MonitoringLocation::findOrFail($id);

        $dateFrom = $request->get('date_from', now()->subMonths(6));
        $dateTo = $request->get('date_to', now());

        $samples = $location->samples()->get();

        $stats = [
            'total_samples' => $samples->count(),
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],

            // WQI Statistics
            'wqi_statistics' => [
                'who' => [
                    'avg' => $samples->avg('wqi_who'),
                    'min' => $samples->min('wqi_who'),
                    'max' => $samples->max('wqi_who'),
                ],
                'nsf' => [
                    'avg' => $samples->avg('wqi_nsf'),
                    'min' => $samples->min('wqi_nsf'),
                    'max' => $samples->max('wqi_nsf'),
                ],
                'ccme' => [
                    'avg' => $samples->avg('wqi_ccme'),
                    'min' => $samples->min('wqi_ccme'),
                    'max' => $samples->max('wqi_ccme'),
                ],
                'custom' => [
                    'avg' => $samples->avg('wqi_custom'),
                    'min' => $samples->min('wqi_custom'),
                    'max' => $samples->max('wqi_custom'),
                ],
            ],

            // Quality Distribution
            'quality_distribution' => $samples->groupBy('quality_status')
                ->map->count(),

            // Risk Level Distribution
            'risk_distribution' => $samples->groupBy('risk_level')
                ->map->count(),

            // Parameter Averages
            'parameter_averages' => [
                'ph' => round($samples->avg('ph'), 2),
                'tds' => round($samples->avg('tds'), 2),
                'turbidity' => round($samples->avg('turbidity'), 2),
                'dissolved_oxygen' => round($samples->avg('dissolved_oxygen'), 2),
                'nitrate' => round($samples->avg('nitrate'), 2),
                'fecal_coliform' => round($samples->avg('fecal_coliform'), 2),
            ],

            // Trend Analysis
            'trend' => $this->calculateDetailedTrend($samples),

            // Time Series Data (for charts)
            'time_series' => $samples->map(function ($sample) {
                return [
                    'date' => $sample->collection_date->format('Y-m-d'),
                    'wqi_who' => $sample->wqi_who,
                    'wqi_nsf' => $sample->wqi_nsf,
                    'wqi_ccme' => $sample->wqi_ccme,
                    'wqi_custom' => $sample->wqi_custom,
                    'quality_status' => $sample->quality_status,
                    'risk_level' => $sample->risk_level,
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Calculate trend for location
     */
    private function calculateTrend($location, $days = 30): array
    {
        $samples = $location->samples()
            ->where('collection_date', '>=', now()->subDays($days))
            ->orderBy('collection_date')
            ->get();

        if ($samples->count() < 2) {
            return ['direction' => 'insufficient_data', 'change' => 0];
        }

        $oldAvg = $samples->take(ceil($samples->count() / 2))->avg('wqi_custom');
        $newAvg = $samples->skip(floor($samples->count() / 2))->avg('wqi_custom');

        $change = $newAvg - $oldAvg;
        $changePercent = $oldAvg > 0 ? ($change / $oldAvg) * 100 : 0;

        if ($changePercent > 5) {
            $direction = 'improving';
        } elseif ($changePercent < -5) {
            $direction = 'declining';
        } else {
            $direction = 'stable';
        }

        return [
            'direction' => $direction,
            'change' => round($change, 2),
            'change_percent' => round($changePercent, 2),
        ];
    }

    /**
     * Calculate detailed trend analysis
     */
    private function calculateDetailedTrend($samples)
    {
        if ($samples->count() < 2) {
            return ['status' => 'insufficient_data'];
        }

        // Linear regression for trend
        $x = range(1, $samples->count());
        $y = $samples->pluck('wqi_custom')->toArray();

        $n = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        return [
            'slope' => round($slope, 4),
            'intercept' => round($intercept, 2),
            'direction' => $slope > 0.1 ? 'improving' : ($slope < -0.1 ? 'declining' : 'stable'),
            'prediction_next_30_days' => round($intercept + $slope * ($n + 30), 2),
        ];
    }
}
