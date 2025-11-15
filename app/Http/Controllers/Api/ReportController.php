<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnalysisReportResource;
use App\Models\AnalysisReport;
use App\Models\WaterSample;
use App\Models\MonitoringLocation;
use App\Services\ReportGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    protected $reportGenerator;

    public function __construct(ReportGeneratorService $reportGenerator)
    {
        $this->reportGenerator = $reportGenerator;
    }

    /**
     * Get all reports
     * GET /api/reports
     */
    public function index(Request $request): JsonResponse
    {
        $query = AnalysisReport::with('generator');

        // Filters
        if ($request->has('report_type')) {
            $query->where('report_type', $request->report_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('generated_by')) {
            $query->where('generated_by', $request->generated_by);
        }

        // Date range
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('report_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $reports = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AnalysisReportResource::collection($reports),
        ]);
    }

    /**
     * Get single report
     * GET /api/reports/{id}
     */
    public function show($id): JsonResponse
    {
        $report = AnalysisReport::with('generator')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Generate new report
     * POST /api/reports/generate
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:single_sample,location_trend,comparative,regional,custom',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'exists:monitoring_locations,id',
            'sample_id' => 'nullable|exists:water_samples,id',
            'parameter_filters' => 'nullable|array',
            'include_charts' => 'boolean',
            'include_recommendations' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create report record
            $reportCode = $this->generateReportCode();

            $report = AnalysisReport::create([
                'report_code' => $reportCode,
                'report_type' => $request->report_type,
                'title' => $request->title,
                'description' => $request->description,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'location_ids' => $request->location_ids,
                'parameter_filters' => $request->parameter_filters,
                'generated_by' => auth()->id() ?? 1,
                'status' => 'completed', // Generate immediately
            ]);

            // Generate report data
            $reportData = $this->reportGenerator->generate($report, $request->all());

            $report->update([
                'summary_statistics' => $reportData['summary'],
                'trends_analysis' => $reportData['trends'],
                'charts_data' => $reportData['charts'],
                'conclusions' => $reportData['conclusions'],
                'recommendations' => $reportData['recommendations'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Report generated successfully',
                'data' => $report->fresh(),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Report generation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Report generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete report
     * DELETE /api/reports/{id}
     */
    public function destroy($id): JsonResponse
    {
        $report = AnalysisReport::findOrFail($id);
        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Report deleted successfully',
        ]);
    }

    /**
     * Get report templates
     * GET /api/reports/templates
     */
    public function templates(): JsonResponse
    {
        $templates = [
            [
                'type' => 'single_sample',
                'name' => 'Single Sample Analysis',
                'description' => 'Detailed analysis of a single water sample',
                'required_fields' => ['sample_id'],
            ],
            [
                'type' => 'location_trend',
                'name' => 'Location Trend Report',
                'description' => 'Water quality trends over time for specific locations',
                'required_fields' => ['location_ids', 'start_date', 'end_date'],
            ],
            [
                'type' => 'comparative',
                'name' => 'Comparative Analysis',
                'description' => 'Compare water quality across multiple locations',
                'required_fields' => ['location_ids', 'start_date', 'end_date'],
            ],
            [
                'type' => 'regional',
                'name' => 'Regional Summary',
                'description' => 'Water quality summary by governorate',
                'required_fields' => ['start_date', 'end_date'],
            ],
            [
                'type' => 'custom',
                'name' => 'Custom Report',
                'description' => 'Build a custom report with selected parameters',
                'required_fields' => ['title', 'parameter_filters'],
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Preview report data before generating
     * POST /api/reports/preview
     */
    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:single_sample,location_trend,comparative,regional,custom',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'location_ids' => 'nullable|array',
            'sample_id' => 'nullable|exists:water_samples,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $preview = $this->generatePreview($request->all());

            return response()->json([
                'success' => true,
                'data' => $preview,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Preview generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get report statistics
     * GET /api/reports/statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_reports' => AnalysisReport::count(),
            'by_type' => AnalysisReport::selectRaw('report_type, COUNT(*) as count')
                ->groupBy('report_type')
                ->pluck('count', 'report_type'),
            'by_status' => AnalysisReport::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'recent_reports' => AnalysisReport::with('generator')
                ->latest()
                ->limit(5)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Generate unique report code
     */
    private function generateReportCode(): string
    {
        $year = date('Y');
        $lastReport = AnalysisReport::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $number = $lastReport ? (int) substr($lastReport->report_code, -4) + 1 : 1;

        return 'RPT-' . $year . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate preview of report data
     */
    private function generatePreview(array $params): array
    {
        $preview = [
            'sample_count' => 0,
            'location_count' => 0,
            'date_range' => null,
            'data_summary' => [],
        ];

        switch ($params['report_type']) {
            case 'single_sample':
                if (isset($params['sample_id'])) {
                    $sample = WaterSample::with('location')->find($params['sample_id']);
                    $preview['sample_count'] = 1;
                    $preview['location_count'] = 1;
                    $preview['data_summary'] = [
                        'sample' => $sample->sample_code,
                        'location' => $sample->location->name,
                        'collection_date' => $sample->collection_date,
                        'wqi' => $sample->wqi_custom,
                    ];
                }
                break;

            case 'location_trend':
            case 'comparative':
                $query = WaterSample::query();

                if (!empty($params['location_ids'])) {
                    $query->whereIn('location_id', $params['location_ids']);
                    $preview['location_count'] = count($params['location_ids']);
                }

                if (!empty($params['start_date'])) {
                    $query->where('collection_date', '>=', $params['start_date']);
                }

                if (!empty($params['end_date'])) {
                    $query->where('collection_date', '<=', $params['end_date']);
                }

                $preview['sample_count'] = $query->count();
                $preview['date_range'] = [
                    'from' => $params['start_date'] ?? null,
                    'to' => $params['end_date'] ?? null,
                ];

                if ($query->count() > 0) {
                    $preview['data_summary'] = [
                        'avg_wqi' => round($query->avg('wqi_custom'), 2),
                        'min_wqi' => round($query->min('wqi_custom'), 2),
                        'max_wqi' => round($query->max('wqi_custom'), 2),
                    ];
                }
                break;

            case 'regional':
                $query = WaterSample::query();

                if (!empty($params['start_date'])) {
                    $query->where('collection_date', '>=', $params['start_date']);
                }

                if (!empty($params['end_date'])) {
                    $query->where('collection_date', '<=', $params['end_date']);
                }

                $preview['sample_count'] = $query->count();
                $preview['location_count'] = $query->distinct('location_id')->count();
                break;
        }

        return $preview;
    }
}
