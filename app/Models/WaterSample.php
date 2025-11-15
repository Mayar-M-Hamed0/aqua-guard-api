<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaterSample extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sample_code',
        'location_id',
        'collected_by',
        'collection_date',
        'collection_time',

        // Physical
        'temperature',
        'turbidity',
        'color',
        'odor_threshold',

        // Chemical - Basic
        'ph',
        'electrical_conductivity',
        'tds',
        'tss',
        'total_hardness',
        'calcium',
        'magnesium',
        'sodium',
        'potassium',
        'chloride',
        'sulfate',
        'alkalinity',

        // Oxygen
        'dissolved_oxygen',
        'bod',
        'cod',

        // Nutrients
        'nitrate',
        'nitrite',
        'ammonia',
        'total_nitrogen',
        'phosphate',
        'total_phosphorus',

        // Heavy Metals
        'lead',
        'mercury',
        'arsenic',
        'cadmium',
        'chromium',
        'copper',
        'iron',
        'manganese',
        'zinc',

        // Microbiological
        'total_coliform',
        'fecal_coliform',
        'e_coli',

        // WQI
        'wqi_who',
        'wqi_nsf',
        'wqi_ccme',
        'wqi_custom',
        'quality_status',

        // AI
        'ai_predictions',
        'ai_confidence',
        'ai_recommendations',

        // Risk
        'risk_level',
        'risk_factors',

        // Lab
        'lab_name',
        'lab_certificate',
        'notes',
        'attachments',

        // Status
        'status',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'collection_date' => 'datetime',
        'verified_at' => 'datetime',
        'ai_predictions' => 'array',
        'risk_factors' => 'array',
        'attachments' => 'array',
    ];

    // Relationships
    public function location(): BelongsTo
    {
        return $this->belongsTo(MonitoringLocation::class, 'location_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(WaterAlert::class, 'sample_id');
    }

    // Scopes
    public function scopeCritical($query)
    {
        return $query->where('risk_level', 'critical');
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('collection_date', '>=', now()->subDays($days));
    }

    public function scopeByLocation($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeByQualityStatus($query, $status)
    {
        return $query->where('quality_status', $status);
    }

    // Accessors
    public function getIsContaminatedAttribute(): bool
    {
        return in_array($this->quality_status, ['poor', 'very_poor'])
            || $this->risk_level === 'critical';
    }

    public function getAverageWqiAttribute(): ?float
    {
        $wqiValues = array_filter([
            $this->wqi_who,
            $this->wqi_nsf,
            $this->wqi_ccme,
            $this->wqi_custom
        ]);

        return !empty($wqiValues) ? round(array_sum($wqiValues) / count($wqiValues), 2) : null;
    }

    // Generate unique sample code
    public static function generateSampleCode(): string
    {
        $year = date('Y');
        $lastSample = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $number = $lastSample ? (int)substr($lastSample->sample_code, -4) + 1 : 1;

        return 'WS-' . $year . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
