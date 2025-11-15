<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaterSample;
use App\Models\MonitoringLocation;
use App\Models\WaterAlert;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get comprehensive dashboard statistics
     * GET /api/dashboard/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subMonths(1));
        $dateTo = $request->get('date_to', now());

        $stats = [
            'summary' => $this->getSummaryStats($dateFrom, $dateTo),
            'quality_distribution' => $this->getQualityDistribution($dateFrom, $dateTo),
            'risk_levels' => $this->getRiskLevelStats($dateFrom, $dateTo),
            'wqi_trends' => $this->getWQITrends($dateFrom, $dateTo),
            'top_contaminated_locations' => $this->getTopContaminatedLocations(5),
            'recent_alerts' => $this->getRecentAlerts(10),
            'parameter_violations' => $this->getParameterViolations($dateFrom, $dateTo),
            'location_performance' => $this->getLocationPerformance($dateFrom, $dateTo),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get summary statistics
     */
    private function getSummaryStats($dateFrom, $dateTo): array
    {
        $totalLocations = MonitoringLocation::active()->count();
        $totalSamples = WaterSample::whereBetween('collection_date', [$dateFrom, $dateTo])->count();
        $activeAlerts = WaterAlert::where('is_resolved', false)->count();
        $criticalLocations = MonitoringLocation::active()
            ->whereHas('samples', function($q) {
                $q->where('risk_level', 'critical')
                  ->latest('collection_date')
                  ->limit(1);
            })
            ->count();

        // Average WQI across all locations
        $avgWQI = WaterSample::whereBetween('collection_date', [$dateFrom, $dateTo])
            ->avg('wqi_custom');

        // Samples collected today
        $samplesToday = WaterSample::whereDate('collection_date', today())->count();

        // Pending analysis
        $pendingAnalysis = WaterSample::where('status', 'pending_analysis')->count();

        return [
            'total_locations' => $totalLocations,
            'total_samples' => $totalSamples,
            'samples_today' => $samplesToday,
            'active_alerts' => $activeAlerts,
            'critical_locations' => $criticalLocations,
            'average_wqi' => round($avgWQI, 2),
            'pending_analysis' => $pendingAnalysis,
            'compliance_rate' => $this->calculateComplianceRate($dateFrom, $dateTo),
        ];
    }

    /**
     * Get quality status distribution
     */
    private function getQualityDistribution($dateFrom, $dateTo): array
    {
        $distribution = WaterSample::whereBetween('collection_date', [$dateFrom, $dateTo])
            ->selectRaw('quality_status, COUNT(*) as count')
            ->groupBy('quality_status')
            ->pluck('count', 'quality_status')
            ->toArray();

        $total = array_sum($distribution);

        return [
            'data' => $distribution,
            'percentages' => array_map(function($count) use ($total) {
                return $total > 0 ? round(($count / $total) * 100, 2) : 0;
            }, $distribution),
            'total' => $total,
        ];
    }

    /**
     * Get risk level statistics
     */
    private function getRiskLevelStats($dateFrom, $dateTo): array
    {
        $distribution = WaterSample::whereBetween('collection_date', [$dateFrom, $dateTo])
            ->selectRaw('risk_level, COUNT(*) as count')
            ->groupBy('risk_level')
            ->pluck('count', 'risk_level')
            ->toArray();

        return [
            'low' => $distribution['low'] ?? 0,
            'medium' => $distribution['medium'] ?? 0,
            'high' => $distribution['high'] ?? 0,
            'critical' => $distribution['critical'] ?? 0,
        ];
    }

    /**
     * Get WQI trends over time
     */
    private function getWQITrends($dateFrom, $dateTo): array
    {
        $samples = WaterSample::whereBetween('collection_date', [$dateFrom, $dateTo])
            ->selectRaw('DATE(collection_date) as date,
                         AVG(wqi_who) as avg_who,
                         AVG(wqi_nsf) as avg_nsf,
                         AVG(wqi_ccme) as avg_ccme,
                         AVG(wqi_custom) as avg_custom,
                         COUNT(*) as sample_count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'timeline' => $samples->map(function($item) {
                return [
                    'date' => $item->date,
                    'wqi_who' => round($item->avg_who, 2),
                    'wqi_nsf' => round($item->avg_nsf, 2),
                    'wqi_ccme' => round($item->avg_ccme, 2),
                    'wqi_custom' => round($item->avg_custom, 2),
                    'sample_count' => $item->sample_count,
                ];
            }),
            'overall_trend' => $this->calculateOverallTrend($samples),
        ];
    }

    /**
     * Get top contaminated locations
     */
    private function getTopContaminatedLocations($limit = 5): array
    {
        return MonitoringLocation::with(['samples' => function($q) {
                $q->latest('collection_date')->limit(1);
            }])
            ->get()
            ->filter(function($location) {
                return $location->samples->isNotEmpty() &&
                       in_array($location->samples->first()->risk_level, ['high', 'critical']);
            })
            ->sortByDesc(function($location) {
                $sample = $location->samples->first();
                return $sample->risk_level === 'critical' ? 2 : 1;
            })
            ->take($limit)
            ->map(function($location) {
                $sample = $location->samples->first();
                return [
                    'location_id' => $location->id,
                    'location_name' => $location->name,
                    'location_code' => $location->code,
                    'type' => $location->type,
                    'risk_level' => $sample->risk_level,
                    'wqi' => $sample->wqi_custom,
                    'quality_status' => $sample->quality_status,
                    'last_sampled' => $sample->collection_date,
                    'risk_factors' => $sample->risk_factors,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get recent alerts
     */
    private function getRecentAlerts($limit = 10): array
    {
        return WaterAlert::with(['sample', 'location'])
            ->where('is_resolved', false)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($alert) {
                return [
                    'id' => $alert->id,
                    'severity' => $alert->severity,
                    'alert_type' => $alert->alert_type,
                    'location_name' => $alert->location->name,
                    'parameter_name' => $alert->parameter_name,
                    'parameter_value' => $alert->parameter_value,
                    'threshold_value' => $alert->threshold_value,
                    'message' => $alert->message,
                    'created_at' => $alert->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get parameter violations summary
     */
    private function getParameterViolations($dateFrom, $dateTo): array
    {
        $violations = [];

        $samples = WaterSample::whereBetween('collection_date', [$dateFrom, $dateTo])
            ->whereNotNull('risk_factors')
            ->get();

        foreach ($samples as $sample) {
            if (!empty($sample->risk_factors)) {
                foreach ($sample->risk_factors as $risk) {
                    $param = $risk['parameter'];
                    if (!isset($violations[$param])) {
                        $violations[$param] = [
                            'parameter' => $param,
                            'count' => 0,
                            'severity_distribution' => [
                                'critical' => 0,
                                'high' => 0,
                                'medium' => 0,
                            ],
                        ];
                    }
                    $violations[$param]['count']++;
                    $violations[$param]['severity_distribution'][$risk['severity']]++;
                }
            }
        }

        // Sort by count
        usort($violations, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        return array_slice($violations, 0, 10); // Top 10 violations
    }

    /**
     * Get location performance comparison
     */
    private function getLocationPerformance($dateFrom, $dateTo): array
    {
        $locations = MonitoringLocation::active()
            ->with(['samples' => function($q) use ($dateFrom, $dateTo) {
                $q->whereBetween('collection_date', [$dateFrom, $dateTo]);
            }])
            ->get();

        return $locations->map(function($location) {
            $samples = $location->samples;

            if ($samples->isEmpty()) {
                return null;
            }

            return [
                'location_id' => $location->id,
                'location_name' => $location->name,
                'type' => $location->type,
                'governorate' => $location->governorate,
                'sample_count' => $samples->count(),
                'avg_wqi' => round($samples->avg('wqi_custom'), 2),
                'excellent_count' => $samples->where('quality_status', 'excellent')->count(),
                'good_count' => $samples->where('quality_status', 'good')->count(),
                'fair_count' => $samples->where('quality_status', 'fair')->count(),
                'poor_count' => $samples->where('quality_status', 'poor')->count(),
                'very_poor_count' => $samples->where('quality_status', 'very_poor')->count(),
                'performance_score' => $this->calculatePerformanceScore($samples),
            ];
        })
        ->filter()
        ->sortByDesc('performance_score')
        ->values()
        ->toArray();
    }

    /**
     * Get regional statistics
     * GET /api/dashboard/regional
     */
    public function regionalStats(Request $request): JsonResponse
    {
        $governorates = MonitoringLocation::active()
            ->whereNotNull('governorate')
            ->pluck('governorate')
            ->unique();

        $stats = [];

        foreach ($governorates as $governorate) {
            $locations = MonitoringLocation::where('governorate', $governorate)->pluck('id');

            $samples = WaterSample::whereIn('location_id', $locations)
                ->where('collection_date', '>=', now()->subMonth())
                ->get();

            if ($samples->isNotEmpty()) {
                $stats[] = [
                    'governorate' => $governorate,
                    'location_count' => $locations->count(),
                    'sample_count' => $samples->count(),
                    'avg_wqi' => round($samples->avg('wqi_custom'), 2),
                    'critical_locations' => $samples->where('risk_level', 'critical')->unique('location_id')->count(),
                    'quality_distribution' => $samples->countBy('quality_status'),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get parameter comparison across locations
     * GET /api/dashboard/parameters/compare
     */
    public function compareParameters(Request $request): JsonResponse
    {
        $locationIds = $request->get('location_ids', []);
        $parameters = $request->get('parameters', ['ph', 'tds', 'turbidity', 'dissolved_oxygen']);

        if (empty($locationIds)) {
            $locationIds = MonitoringLocation::active()->limit(5)->pluck('id')->toArray();
        }

        $comparison = [];

        foreach ($locationIds as $locationId) {
            $location = MonitoringLocation::find($locationId);

            if (!$location) continue;

            $latestSample = $location->samples()->latest('collection_date')->first();

            if ($latestSample) {
                $data = ['location_name' => $location->name];

                foreach ($parameters as $param) {
                    $data[$param] = $latestSample->$param;
                }

                $comparison[] = $data;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $comparison,
        ]);
    }

    /**
     * Helper: Calculate compliance rate
     */
    private function calculateComplianceRate($dateFrom, $dateTo): float
    {
        $total = WaterSample::whereBetween('collection_date', [$dateFrom, $dateTo])->count();

        if ($total === 0) return 0;

        $compliant = WaterSample::whereBetween('collection_date', [$dateFrom, $dateTo])
            ->whereIn('quality_status', ['excellent', 'good'])
            ->count();

        return round(($compliant / $total) * 100, 2);
    }

    /**
     * Helper: Calculate overall trend
     */
    private function calculateOverallTrend($samples)
    {
        if ($samples->count() < 2) {
            return 'insufficient_data';
        }

        $first = $samples->first()->avg_custom;
        $last = $samples->last()->avg_custom;

        $change = $last - $first;

        if ($change > 5) return 'improving';
        if ($change < -5) return 'declining';
        return 'stable';
    }

    /**
     * Helper: Calculate performance score
     */
    private function calculatePerformanceScore($samples): float
    {
        if ($samples->isEmpty()) return 0;

        $avgWQI = $samples->avg('wqi_custom');
        $consistencyScore = 100 - ($samples->pluck('wqi_custom')->values()->std() ?? 0);

        return round(($avgWQI * 0.7) + ($consistencyScore * 0.3), 2);
    }
}
