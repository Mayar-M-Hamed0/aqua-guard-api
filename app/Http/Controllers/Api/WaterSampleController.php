<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaterSample;
use App\Services\WQICalculatorService;
use App\Services\AIAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WaterSampleController extends Controller
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
     * Get all samples with filters
     * GET /api/samples
     */
    public function index(Request $request): JsonResponse
    {
        $query = WaterSample::with(['location', 'collector']);

        // Filters
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->has('quality_status')) {
            $query->where('quality_status', $request->quality_status);
        }

        if ($request->has('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }

        if ($request->has('date_from')) {
            $query->where('collection_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('collection_date', '<=', $request->date_to);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('sample_code', 'like', "%{$search}%")
                  ->orWhereHas('location', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'collection_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $samples = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $samples,
        ]);
    }

    /**
     * Get single sample details
     * GET /api/samples/{id}
     */
    public function show($id): JsonResponse
    {
        $sample = WaterSample::with([
            'location',
            'collector',
            'verifier',
            'alerts' => function($q) {
                $q->latest()->limit(10);
            }
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $sample,
        ]);
    }

    /**
     * Create new water sample
     * POST /api/samples
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location_id' => 'required|exists:monitoring_locations,id',
            'collection_date' => 'required|date',
            'collection_time' => 'nullable|date_format:H:i',

            // At least one parameter is required
            'ph' => 'nullable|numeric|between:0,14',
            'temperature' => 'nullable|numeric',
            'turbidity' => 'nullable|numeric|min:0',
            'tds' => 'nullable|numeric|min:0',
            'dissolved_oxygen' => 'nullable|numeric|min:0',
            'bod' => 'nullable|numeric|min:0',
            'cod' => 'nullable|numeric|min:0',
            'nitrate' => 'nullable|numeric|min:0',
            'nitrite' => 'nullable|numeric|min:0',
            'ammonia' => 'nullable|numeric|min:0',
            'phosphate' => 'nullable|numeric|min:0',
            'total_coliform' => 'nullable|integer|min:0',
            'fecal_coliform' => 'nullable|integer|min:0',
            'e_coli' => 'nullable|integer|min:0',

            // Heavy metals
            'lead' => 'nullable|numeric|min:0',
            'mercury' => 'nullable|numeric|min:0',
            'arsenic' => 'nullable|numeric|min:0',
            'cadmium' => 'nullable|numeric|min:0',

            // Lab info
            'lab_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Generate sample code
            $sampleCode = WaterSample::generateSampleCode();

            // Create sample
            $sample = WaterSample::create([
                'sample_code' => $sampleCode,
                'location_id' => $request->location_id,
                'collected_by' => auth()->id(),
                'collection_date' => $request->collection_date,
                'collection_time' => $request->collection_time,

                // Physical
                'temperature' => $request->temperature,
                'turbidity' => $request->turbidity,
                'color' => $request->color,

                // Chemical
                'ph' => $request->ph,
                'electrical_conductivity' => $request->electrical_conductivity,
                'tds' => $request->tds,
                'tss' => $request->tss,
                'total_hardness' => $request->total_hardness,
                'calcium' => $request->calcium,
                'magnesium' => $request->magnesium,
                'chloride' => $request->chloride,
                'sulfate' => $request->sulfate,
                'alkalinity' => $request->alkalinity,

                // Oxygen
                'dissolved_oxygen' => $request->dissolved_oxygen,
                'bod' => $request->bod,
                'cod' => $request->cod,

                // Nutrients
                'nitrate' => $request->nitrate,
                'nitrite' => $request->nitrite,
                'ammonia' => $request->ammonia,
                'total_nitrogen' => $request->total_nitrogen,
                'phosphate' => $request->phosphate,
                'total_phosphorus' => $request->total_phosphorus,

                // Heavy Metals
                'lead' => $request->lead,
                'mercury' => $request->mercury,
                'arsenic' => $request->arsenic,
                'cadmium' => $request->cadmium,
                'chromium' => $request->chromium,
                'copper' => $request->copper,
                'iron' => $request->iron,
                'manganese' => $request->manganese,
                'zinc' => $request->zinc,

                // Microbiological
                'total_coliform' => $request->total_coliform,
                'fecal_coliform' => $request->fecal_coliform,
                'e_coli' => $request->e_coli,

                // Lab
                'lab_name' => $request->lab_name,
                'notes' => $request->notes,

                'status' => 'pending_analysis',
            ]);

            // Calculate WQI automatically
            $wqiResults = $this->wqiCalculator->calculateAllWQI($sample);
            $sample->update($wqiResults);

            // Run AI Analysis (async in production)
            try {
                $aiResults = $this->aiAnalysis->analyze($sample);
                $sample->update([
                    'ai_predictions' => $aiResults['predictions'],
                    'ai_confidence' => $aiResults['confidence'],
                    'ai_recommendations' => $aiResults['recommendations'],
                ]);
            } catch (\Exception $e) {
                Log::warning('AI Analysis failed: ' . $e->getMessage());
            }

            // Create alerts if needed
            $this->createAlertsIfNeeded($sample);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Water sample created and analyzed successfully',
                'data' => $sample->load(['location', 'collector']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sample: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing sample
     * PUT /api/samples/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $sample = WaterSample::findOrFail($id);

        // Only allow updating if not verified
        if ($sample->status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update verified sample'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ph' => 'nullable|numeric|between:0,14',
            'temperature' => 'nullable|numeric',
            'turbidity' => 'nullable|numeric|min:0',
            // ... same validation as store
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $sample->update($request->only([
                'temperature', 'turbidity', 'ph', 'tds', 'dissolved_oxygen',
                'bod', 'cod', 'nitrate', 'nitrite', 'ammonia', 'phosphate',
                'total_coliform', 'fecal_coliform', 'e_coli',
                'lead', 'mercury', 'arsenic', 'cadmium',
                'lab_name', 'notes'
            ]));

            // Recalculate WQI
            $wqiResults = $this->wqiCalculator->calculateAllWQI($sample);
            $sample->update($wqiResults);

            // Re-run AI Analysis
            try {
                $aiResults = $this->aiAnalysis->analyze($sample);
                $sample->update([
                    'ai_predictions' => $aiResults['predictions'],
                    'ai_confidence' => $aiResults['confidence'],
                    'ai_recommendations' => $aiResults['recommendations'],
                ]);
            } catch (\Exception $e) {
                Log::warning('AI Analysis failed: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sample updated successfully',
                'data' => $sample->fresh(['location', 'collector']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sample: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete sample (soft delete)
     * DELETE /api/samples/{id}
     */
    public function destroy($id): JsonResponse
    {
        $sample = WaterSample::findOrFail($id);

        // Only admin can delete verified samples
        if ($sample->status === 'verified' && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin can delete verified samples'
            ], 403);
        }

        $sample->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sample deleted successfully',
        ]);
    }

    /**
     * Verify sample (for quality control)
     * POST /api/samples/{id}/verify
     */
    public function verify($id): JsonResponse
    {
        $sample = WaterSample::findOrFail($id);

        if ($sample->status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Sample already verified'
            ], 400);
        }

        $sample->update([
            'status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sample verified successfully',
            'data' => $sample->fresh(['verifier']),
        ]);
    }

    /**
     * Batch import samples from Excel/CSV
     * POST /api/samples/import
     */
    public function import(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'location_id' => 'required|exists:monitoring_locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Import logic using Maatwebsite\Excel
            $imported = \Excel::import(new WaterSamplesImport($request->location_id), $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Samples imported successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get samples for map visualization
     * GET /api/samples/map-data
     */
    public function mapData(Request $request): JsonResponse
    {
        $query = WaterSample::with('location')
            ->select('water_samples.*')
            ->join('monitoring_locations', 'water_samples.location_id', '=', 'monitoring_locations.id')
            ->selectRaw('monitoring_locations.latitude, monitoring_locations.longitude');

        if ($request->has('date_from')) {
            $query->where('collection_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('collection_date', '<=', $request->date_to);
        }

        // Get latest sample per location for map
        $samples = $query->get()->groupBy('location_id')->map(function($group) {
            return $group->sortByDesc('collection_date')->first();
        })->values();

        return response()->json([
            'success' => true,
            'data' => $samples,
        ]);
    }

    /**
     * Create alerts based on sample analysis
     */
    private function createAlertsIfNeeded(WaterSample $sample): void
    {
        if ($sample->risk_level === 'critical' || $sample->risk_level === 'high') {
            foreach ($sample->risk_factors as $risk) {
                \App\Models\WaterAlert::create([
                    'sample_id' => $sample->id,
                    'location_id' => $sample->location_id,
                    'severity' => $risk['severity'],
                    'alert_type' => 'parameter_exceeded',
                    'parameter_name' => $risk['parameter'],
                    'message' => $risk['message'],
                    'affected_parameters' => [$risk['parameter']],
                ]);
            }
        }
    }
}
