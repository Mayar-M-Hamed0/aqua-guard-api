<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WqiTrendResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'date' => $this->date->toISOString(),
            'avg_wqi' => $this->avg_wqi,
            'min_wqi' => $this->min_wqi,
            'max_wqi' => $this->max_wqi,
            'sample_count' => $this->sample_count,
            'trend_direction' => $this->trend_direction,
            'parameter_averages' => $this->parameter_averages,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'location' => new MonitoringLocationResource($this->whenLoaded('location')),
        ];
    }
}
