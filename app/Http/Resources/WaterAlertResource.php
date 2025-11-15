<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaterAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sample_id' => $this->sample_id,
            'location_id' => $this->location_id,
            'severity' => $this->severity,
            'alert_type' => $this->alert_type,
            'parameter_name' => $this->parameter_name,
            'parameter_value' => $this->parameter_value,
            'threshold_value' => $this->threshold_value,
            'message' => $this->message,
            'affected_parameters' => $this->affected_parameters,
            'is_read' => $this->is_read,
            'is_resolved' => $this->is_resolved,
            'resolved_by' => $this->whenLoaded('resolver'),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'resolution_notes' => $this->resolution_notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'sample' => new WaterSampleResource($this->whenLoaded('sample')),
            'location' => new MonitoringLocationResource($this->whenLoaded('location')),
        ];
    }
}
