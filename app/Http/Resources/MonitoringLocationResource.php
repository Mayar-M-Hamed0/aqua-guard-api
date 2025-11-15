<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitoringLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,
            'type' => $this->type,
            'governorate' => $this->governorate,
            'city' => $this->city,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
            'created_by' => $this->whenLoaded('creator'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'samples_count' => $this->whenCounted('samples'),
            'latest_sample' => new WaterSampleResource($this->whenLoaded('latestSample')),
            'alerts' => WaterAlertResource::collection($this->whenLoaded('alerts')),
        ];
    }
}
