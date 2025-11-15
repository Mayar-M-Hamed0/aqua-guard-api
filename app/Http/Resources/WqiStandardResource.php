<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WqiStandardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'water_type' => $this->water_type,
            'parameters_config' => $this->parameters_config,
            'calculation_method' => $this->calculation_method,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'parameters' => ParameterStandardResource::collection($this->whenLoaded('parameters')),
        ];
    }
}
