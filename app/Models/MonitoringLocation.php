<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'latitude',
        'longitude',
        'address',
        'type',
        'governorate',
        'city',
        'is_active',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    // Relationships
    public function samples(): HasMany
    {
        return $this->hasMany(WaterSample::class, 'location_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(WaterAlert::class, 'location_id');
    }

    public function trends(): HasMany
    {
        return $this->hasMany(WqiTrend::class, 'location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeNearby($query, $latitude, $longitude, $radiusKm = 10)
    {
        // Haversine formula for distance calculation
        $query->selectRaw("
            *,
            (6371 * acos(
                cos(radians(?)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(latitude))
            )) AS distance
        ", [$latitude, $longitude, $latitude])
        ->having('distance', '<=', $radiusKm)
        ->orderBy('distance');

        return $query;
    }

    // Accessors
    public function getLatestSampleAttribute()
    {
        return $this->samples()->latest('collection_date')->first();
    }

    public function getAverageWqiAttribute(): ?float
    {
        return $this->samples()
            ->whereNotNull('wqi_custom')
            ->avg('wqi_custom');
    }

    public function getCurrentRiskLevelAttribute(): ?string
    {
        return $this->samples()
            ->latest('collection_date')
            ->first()
            ?->risk_level;
    }

    // Generate unique location code
    public static function generateLocationCode(): string
    {
        $lastLocation = self::orderBy('id', 'desc')->first();
        $number = $lastLocation ? (int)substr($lastLocation->code, -3) + 1 : 1;

        return 'LOC-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
}
