<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'sample_id',
        'location_id',
        'severity',
        'alert_type',
        'parameter_name',
        'parameter_value',
        'threshold_value',
        'message',
        'affected_parameters',
        'is_read',
        'is_resolved',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'affected_parameters' => 'array',
        'parameter_value' => 'decimal:4',
        'threshold_value' => 'decimal:4',
        'is_read' => 'boolean',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    // Relationships
    public function sample(): BelongsTo
    {
        return $this->belongsTo(WaterSample::class, 'sample_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(MonitoringLocation::class, 'location_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    // Accessors
    public function getIsCriticalAttribute(): bool
    {
        return in_array($this->severity, ['critical', 'emergency']);
    }

    public function getFormattedMessageAttribute(): string
    {
        if ($this->parameter_name && $this->parameter_value) {
            return sprintf(
                '%s: %s exceeds threshold of %s',
                ucfirst(str_replace('_', ' ', $this->parameter_name)),
                $this->parameter_value,
                $this->threshold_value
            );
        }
        return $this->message;
    }

    // Methods
    public function markAsRead(): bool
    {
        return $this->update(['is_read' => true]);
    }

    public function resolve(int $userId, ?string $notes = null): bool
    {
        return $this->update([
            'is_resolved' => true,
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }

    // Static Methods
    public static function createFromSample(WaterSample $sample, array $riskFactors): void
    {
        foreach ($riskFactors as $risk) {
            self::create([
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
