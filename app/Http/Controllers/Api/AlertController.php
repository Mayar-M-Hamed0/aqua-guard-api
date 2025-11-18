<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaterAlert;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AlertController extends Controller
{
    /**
     * Get all alerts with filters
     * GET /api/alerts
     */
    public function index(Request $request): JsonResponse
    {
        $query = WaterAlert::with(['sample', 'location']);

        // Filters
        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->has('is_resolved')) {
            $query->where('is_resolved', $request->boolean('is_resolved'));
        }

        if ($request->has('alert_type')) {
            $query->where('alert_type', $request->alert_type);
        }

        // Date range
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 20);
        $alerts = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    /**
     * Get unread alerts
     * GET /api/alerts/unread
     */
    public function unread(): JsonResponse
    {
        $alerts = WaterAlert::with(['sample', 'location'])
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $alerts,
            'count' => $alerts->count(),
        ]);
    }

    /**
     * Mark alert as read
     * POST /api/alerts/{id}/mark-read
     */
    public function markAsRead($id): JsonResponse
    {
        $alert = WaterAlert::findOrFail($id);

        $alert->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Alert marked as read',
            'data' => $alert,
        ]);
    }

    /**
     * Resolve alert
     * POST /api/alerts/{id}/resolve
     */
    public function resolve($id, Request $request): JsonResponse
    {
        $alert = WaterAlert::findOrFail($id);

        $alert->update([
            'is_resolved' => true,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'resolution_notes' => $request->input('resolution_notes'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alert resolved successfully',
            'data' => $alert->fresh(['sample', 'location']),
        ]);
    }

    /**
     * Delete alert
     * DELETE /api/alerts/{id}
     */
    public function destroy($id): JsonResponse
    {
        $alert = WaterAlert::findOrFail($id);
        $alert->delete();

        return response()->json([
            'success' => true,
            'message' => 'Alert deleted successfully',
        ]);
    }

    /**
     * Get alert statistics
     * GET /api/alerts/statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_alerts' => WaterAlert::count(),
            'unread_count' => WaterAlert::where('is_read', false)->count(),
            'unresolved_count' => WaterAlert::where('is_resolved', false)->count(),
            'by_severity' => WaterAlert::selectRaw('severity, COUNT(*) as count')
                ->groupBy('severity')
                ->pluck('count', 'severity'),
            'by_type' => WaterAlert::selectRaw('alert_type, COUNT(*) as count')
                ->groupBy('alert_type')
                ->pluck('count', 'alert_type'),
            'recent_critical' => WaterAlert::with(['sample', 'location'])
                ->where('severity', 'critical')
                ->where('is_resolved', false)
                ->latest()
                ->limit(10)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Mark multiple alerts as read
     * POST /api/alerts/mark-all-read
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $alertIds = $request->input('alert_ids', []);

        if (empty($alertIds)) {
            WaterAlert::where('is_read', false)->update(['is_read' => true]);
            $message = 'All alerts marked as read';
        } else {
            WaterAlert::whereIn('id', $alertIds)->update(['is_read' => true]);
            $message = count($alertIds) . ' alerts marked as read';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }
}
