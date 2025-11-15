<?php

namespace App\Services;

use App\Models\AnalysisReport;
use App\Models\WaterSample;
use App\Models\MonitoringLocation;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportGeneratorService
{
    /**
     * Generate report based on type and parameters
     */
    public function generate(AnalysisReport $report, array $params): array
    {
        $data = match($report->report_type) {
            'single_sample' => $this->generateSingleSampleReport($params),
            'location_trend' => $this->generateLocationTrendReport($params),
            'comparative' => $this->generateComparativeReport($params),
            'regional' => $this->generateRegionalReport($params),
            'custom' => $this->generateCustomReport($params),
            default => throw new \Exception('Unknown report type'),
        };

        // Generate files
        $pdfPath = null;
        $excelPath = null;

        if (in_array($params['format'] ?? 'pdf', ['pdf', 'both'])) {
            $pdfPath = $this->generatePDF($report, $data);
        }

        if (in_array($params['format'] ?? 'pdf', ['excel', 'both'])) {
            $excelPath = $this->generateExcel($report, $data);
        }

        return [
            'summary' => $data['summary'],
            'trends' => $data['trends'],
            'charts' => $data['charts'],
            'conclusions' => $data['conclusions'],
            'recommendations' => $data['recommendations'],
            'pdf_path' => $pdfPath,
            'excel_path' => $excelPath,
        ];
    }

    /**
     * Generate Single Sample Analysis Report
     */
    private function generateSingleSampleReport(array $params): array
    {
        $sample = WaterSample::with('location')->findOrFail($params['sample_id']);

        return [
            'summary' => [
                'sample_code' => $sample->sample_code,
                'location' => $sample->location->name,
                'collection_date' => $sample->collection_date,
                'wqi_scores' => [
                    'who' => $sample->wqi_who,
                    'nsf' => $sample->wqi_nsf,
                    'ccme' => $sample->wqi_ccme,
                    'custom' => $sample->wqi_custom,
                ],
                'quality_status' => $sample->quality_status,
                'risk_level' => $sample->risk_level,
            ],
            'trends' => null,
            'charts' => $this->generateSampleCharts($sample),
            'conclusions' => $this->generateSampleConclusions($sample),
            'recommendations' => $sample->ai_recommendations ?? [],
        ];
    }

    /**
     * Generate Location Trend Report
     */
    private function generateLocationTrendReport(array $params): array
    {
        $locationIds = $params['location_ids'];
        $startDate = $params['start_date'];
        $endDate = $params['end_date'];

        $samples = WaterSample::whereIn('location_id', $locationIds)
            ->whereBetween('collection_date', [$startDate, $endDate])
            ->orderBy('collection_date')
            ->get();

        return [
            'summary' => [
                'total_samples' => $samples->count(),
                'date_range' => "{$startDate} to {$endDate}",
                'avg_wqi' => round($samples->avg('wqi_custom'), 2),
                'locations_count' => count($locationIds),
            ],
            'trends' => $this->analyzeTrends($samples),
            'charts' => $this->generateTrendCharts($samples),
            'conclusions' => $this->generateTrendConclusions($samples),
            'recommendations' => $this->generateTrendRecommendations($samples),
        ];
    }

    /**
     * Generate Comparative Report
     */
    private function generateComparativeReport(array $params): array
    {
        $locationIds = $params['location_ids'];
        $startDate = $params['start_date'];
        $endDate = $params['end_date'];

        $comparison = [];

        foreach ($locationIds as $locationId) {
            $location = MonitoringLocation::find($locationId);
            $samples = WaterSample::where('location_id', $locationId)
                ->whereBetween('collection_date', [$startDate, $endDate])
                ->get();

            $comparison[] = [
                'location' => $location->name,
                'sample_count' => $samples->count(),
                'avg_wqi' => round($samples->avg('wqi_custom'), 2),
                'quality_distribution' => $samples->countBy('quality_status'),
            ];
        }

        return [
            'summary' => [
                'locations_compared' => count($locationIds),
                'date_range' => "{$startDate} to {$endDate}",
                'comparison' => $comparison,
            ],
            'trends' => null,
            'charts' => $this->generateComparisonCharts($comparison),
            'conclusions' => $this->generateComparisonConclusions($comparison),
            'recommendations' => [],
        ];
    }

    /**
     * Generate Regional Report
     */
    private function generateRegionalReport(array $params): array
    {
        $startDate = $params['start_date'];
        $endDate = $params['end_date'];

        $samples = WaterSample::whereBetween('collection_date', [$startDate, $endDate])->get();

        $byGovernorate = $samples->groupBy(function($sample) {
            return $sample->location->governorate;
        });

        $regionalData = [];

        foreach ($byGovernorate as $governorate => $govSamples) {
            $regionalData[] = [
                'governorate' => $governorate,
                'sample_count' => $govSamples->count(),
                'avg_wqi' => round($govSamples->avg('wqi_custom'), 2),
                'locations' => $govSamples->unique('location_id')->count(),
            ];
        }

        return [
            'summary' => [
                'total_samples' => $samples->count(),
                'governorates' => count($regionalData),
                'date_range' => "{$startDate} to {$endDate}",
                'regional_data' => $regionalData,
            ],
            'trends' => null,
            'charts' => $this->generateRegionalCharts($regionalData),
            'conclusions' => $this->generateRegionalConclusions($regionalData),
            'recommendations' => [],
        ];
    }

    /**
     * Generate Custom Report
     */
    private function generateCustomReport(array $params): array
    {
        // Custom logic based on parameter_filters
        return [
            'summary' => ['status' => 'custom'],
            'trends' => null,
            'charts' => [],
            'conclusions' => 'Custom report generated',
            'recommendations' => [],
        ];
    }

    /**
     * Generate PDF file
     */
    private function generatePDF(AnalysisReport $report, array $data): string
    {
        $pdf = Pdf::loadView('reports.pdf', [
            'report' => $report,
            'data' => $data,
        ]);

        $filename = "reports/{$report->report_code}.pdf";
        Storage::put($filename, $pdf->output());

        return $filename;
    }

    /**
     * Generate Excel file
     */
    private function generateExcel(AnalysisReport $report, array $data): string
    {
        // Use Laravel Excel or PhpSpreadsheet
        $filename = "reports/{$report->report_code}.xlsx";

        // Implementation here...

        return $filename;
    }

    // Helper methods for charts and analysis...

    private function generateSampleCharts($sample): array
    {
        return [
            'wqi_comparison' => [
                'labels' => ['WHO', 'NSF', 'CCME', 'Egyptian'],
                'values' => [$sample->wqi_who, $sample->wqi_nsf, $sample->wqi_ccme, $sample->wqi_custom],
            ],
            'parameter_radar' => [
                'parameters' => ['pH', 'TDS', 'Turbidity', 'DO', 'Nitrate'],
                'values' => [$sample->ph, $sample->tds, $sample->turbidity, $sample->dissolved_oxygen, $sample->nitrate],
            ],
        ];
    }

    private function analyzeTrends($samples): array
    {
        if ($samples->count() < 2) {
            return ['status' => 'insufficient_data'];
        }

        $wqiValues = $samples->pluck('wqi_custom')->toArray();
        $dates = $samples->pluck('collection_date')->toArray();

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

        return [
            'direction' => $slope > 0.1 ? 'improving' : ($slope < -0.1 ? 'declining' : 'stable'),
            'slope' => round($slope, 4),
            'change_rate' => round($slope * 100, 2) . '% per sample',
        ];
    }

    private function generateTrendCharts($samples): array
    {
        return [
            'wqi_over_time' => [
                'dates' => $samples->pluck('collection_date')->map(fn($d) => $d->format('Y-m-d'))->toArray(),
                'wqi_values' => $samples->pluck('wqi_custom')->toArray(),
            ],
        ];
    }

    private function generateComparisonCharts($comparison): array
    {
        return [
            'location_comparison' => [
                'locations' => array_column($comparison, 'location'),
                'avg_wqi' => array_column($comparison, 'avg_wqi'),
            ],
        ];
    }

    private function generateRegionalCharts($regionalData): array
    {
        return [
            'governorate_map' => [
                'governorates' => array_column($regionalData, 'governorate'),
                'avg_wqi' => array_column($regionalData, 'avg_wqi'),
            ],
        ];
    }

    private function generateSampleConclusions($sample): string
    {
        $quality = $sample->quality_status;
        return "Water sample shows {$quality} quality with WQI of {$sample->wqi_custom}.";
    }

    private function generateTrendConclusions($samples): string
    {
        $avgWqi = round($samples->avg('wqi_custom'), 2);
        return "Average WQI over period: {$avgWqi}. " . ($avgWqi >= 70 ? 'Generally acceptable quality.' : 'Requires attention.');
    }

    private function generateComparisonConclusions($comparison): string
    {
        $best = collect($comparison)->sortByDesc('avg_wqi')->first();
        return "Best performing location: {$best['location']} with WQI {$best['avg_wqi']}.";
    }

    private function generateRegionalConclusions($regionalData): string
    {
        return "Regional analysis covers " . count($regionalData) . " governorates.";
    }

    private function generateTrendRecommendations($samples): array
    {
        $recommendations = [];

        $avgWqi = $samples->avg('wqi_custom');

        if ($avgWqi < 50) {
            $recommendations[] = 'Immediate action required - water quality below acceptable standards';
        }

        return $recommendations;
    }
}
