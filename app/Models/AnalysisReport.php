<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class AnalysisReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_code',
        'report_type',
        'title',
        'description',
        'start_date',
        'end_date',
        'location_ids',
        'parameter_filters',
        'summary_statistics',
        'trends_analysis',
        'charts_data',
        'conclusions',
        'recommendations',
        'pdf_path',
        'excel_path',
        'generated_by',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'location_ids' => 'array',
        'parameter_filters' => 'array',
        'summary_statistics' => 'array',
        'trends_analysis' => 'array',
        'charts_data' => 'array',
        'recommendations' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'status_label',
        'type_label',
        'has_files',
        'duration_days',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * User who generated the report
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get locations included in this report
     */
    public function locations()
    {
        if (empty($this->location_ids)) {
            return collect([]);
        }

        return MonitoringLocation::whereIn('id', $this->location_ids)->get();
    }

    // ==================== ACCESSORS ====================

    /**
     * Get human-readable status
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'generating' => 'Generating',
            'completed' => 'Completed',
            'failed' => 'Failed',
            default => $this->status,
        };
    }

    /**
     * Get human-readable report type
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->report_type) {
            'single_sample' => 'Single Sample Analysis',
            'location_trend' => 'Location Trend Analysis',
            'comparative' => 'Comparative Analysis',
            'regional' => 'Regional Summary',
            'custom' => 'Custom Report',
            default => $this->report_type,
        };
    }

    /**
     * Check if report has generated files
     */
    public function getHasFilesAttribute(): bool
    {
        return !empty($this->pdf_path) || !empty($this->excel_path);
    }

    /**
     * Get report duration in days
     */
    public function getDurationDaysAttribute(): ?int
    {
        if (!$this->start_date || !$this->end_date) {
            return null;
        }

        $start = $this->start_date instanceof Carbon ? $this->start_date : Carbon::parse($this->start_date);
        $end = $this->end_date instanceof Carbon ? $this->end_date : Carbon::parse($this->end_date);

        return $start->diffInDays($end);
    }

    /**
     * Get PDF download URL
     */
    public function getPdfUrlAttribute(): ?string
    {
        return $this->pdf_path ? Storage::url($this->pdf_path) : null;
    }

    /**
     * Get Excel download URL
     */
    public function getExcelUrlAttribute(): ?string
    {
        return $this->excel_path ? Storage::url($this->excel_path) : null;
    }

    // ==================== SCOPES ====================

    public function scopeOfType($query, string $type)
    {
        return $query->where('report_type', $type);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeForLocation($query, int $locationId)
    {
        return $query->whereJsonContains('location_ids', $locationId);
    }

    public function scopeGeneratedBy($query, int $userId)
    {
        return $query->where('generated_by', $userId);
    }

    public function scopeRecent($query, int $limit = 10)
    {
        return $query->latest()->limit($limit);
    }

    // ==================== METHODS ====================

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isGenerating(): bool
    {
        return $this->status === 'generating';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markAsCompleted(): bool
    {
        return $this->update(['status' => 'completed']);
    }

    public function markAsFailed(): bool
    {
        return $this->update(['status' => 'failed']);
    }

    public function hasPdf(): bool
    {
        return !empty($this->pdf_path) && Storage::exists($this->pdf_path);
    }

    public function hasExcel(): bool
    {
        return !empty($this->excel_path) && Storage::exists($this->excel_path);
    }

    public function getFileSize(string $type = 'pdf'): ?int
    {
        $path = $type === 'excel' ? $this->excel_path : $this->pdf_path;
        if (!$path || !Storage::exists($path)) {
            return null;
        }

        return round(Storage::size($path) / 1024);
    }

    public function deleteFiles(): void
    {
        if ($this->pdf_path && Storage::exists($this->pdf_path)) {
            Storage::delete($this->pdf_path);
        }

        if ($this->excel_path && Storage::exists($this->excel_path)) {
            Storage::delete($this->excel_path);
        }
    }

    public function getSummaryValue(string $key, $default = null)
    {
        return data_get($this->summary_statistics, $key, $default);
    }

    public function getChartData(string $chartType): ?array
    {
        return $this->charts_data[$chartType] ?? null;
    }

    // ==================== EVENTS ====================

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($report) {
            $report->deleteFiles();
        });
    }
}
