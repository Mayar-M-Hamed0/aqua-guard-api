<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParameterStandardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'standard_id' => $this->standard_id,
            'parameter_name' => $this->parameter_name,
            'ideal_value' => $this->ideal_value,
            'min_acceptable' => $this->min_acceptable,
            'max_acceptable' => $this->max_acceptable,
            'min_permissible' => $this->min_permissible,
            'max_permissible' => $this->max_permissible,
            'unit' => $this->unit,
            'weight' => $this->weight,
            'health_impact' => $this->health_impact,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'standard' => new WqiStandardResource($this->whenLoaded('standard')),
        ];
    }
}
