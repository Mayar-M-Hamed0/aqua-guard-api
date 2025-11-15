<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaterSampleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sample_code' => $this->sample_code,

            // Location Info
            'location_id' => $this->location_id,
            'location' => new MonitoringLocationResource($this->whenLoaded('location')),

            // Collection Info
            'collected_by' => $this->whenLoaded('collector'),
            'collection_date' => $this->collection_date->toISOString(),
            'collection_time' => $this->collection_time,

            // Physical Parameters
            'physical_parameters' => [
                'temperature' => $this->temperature,
                'turbidity' => $this->turbidity,
                'color' => $this->color,
                'odor_threshold' => $this->odor_threshold,
            ],

            // Chemical Parameters
            'chemical_parameters' => [
                'ph' => $this->ph,
                'electrical_conductivity' => $this->electrical_conductivity,
                'tds' => $this->tds,
                'tss' => $this->tss,
                'total_hardness' => $this->total_hardness,
                'calcium' => $this->calcium,
                'magnesium' => $this->magnesium,
                'sodium' => $this->sodium,
                'potassium' => $this->potassium,
                'chloride' => $this->chloride,
                'sulfate' => $this->sulfate,
                'alkalinity' => $this->alkalinity,
            ],

            // Oxygen Parameters
            'oxygen_parameters' => [
                'dissolved_oxygen' => $this->dissolved_oxygen,
                'bod' => $this->bod,
                'cod' => $this->cod,
            ],

            // Nutrients
            'nutrients' => [
                'nitrate' => $this->nitrate,
                'nitrite' => $this->nitrite,
                'ammonia' => $this->ammonia,
                'total_nitrogen' => $this->total_nitrogen,
                'phosphate' => $this->phosphate,
                'total_phosphorus' => $this->total_phosphorus,
            ],

            // Heavy Metals
            'heavy_metals' => [
                'lead' => $this->lead,
                'mercury' => $this->mercury,
                'arsenic' => $this->arsenic,
                'cadmium' => $this->cadmium,
                'chromium' => $this->chromium,
                'copper' => $this->copper,
                'iron' => $this->iron,
                'manganese' => $this->manganese,
                'zinc' => $this->zinc,
            ],

            // Microbiological
            'microbiological' => [
                'total_coliform' => $this->total_coliform,
                'fecal_coliform' => $this->fecal_coliform,
                'e_coli' => $this->e_coli,
            ],

            // WQI Calculations
            'wqi_scores' => [
                'who' => $this->wqi_who,
                'nsf' => $this->wqi_nsf,
                'ccme' => $this->wqi_ccme,
                'custom' => $this->wqi_custom,
            ],

            'quality_status' => $this->quality_status,

            // AI & Risk Assessment
            'ai_predictions' => $this->ai_predictions,
            'ai_confidence' => $this->ai_confidence,
            'ai_recommendations' => $this->ai_recommendations,
            'risk_level' => $this->risk_level,
            'risk_factors' => $this->risk_factors,

            // Lab Info
            'lab_name' => $this->lab_name,
            'lab_certificate' => $this->lab_certificate,
            'notes' => $this->notes,
            'attachments' => $this->attachments,

            // Status & Verification
            'status' => $this->status,
            'verified_by' => $this->whenLoaded('verifier'),
            'verified_at' => $this->verified_at?->toISOString(),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'alerts' => WaterAlertResource::collection($this->whenLoaded('alerts')),
        ];
    }
}
