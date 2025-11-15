<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_code' => $this->report_code,
            'report_type' => $this->report_type,
            'title' => $this->title,
            'description' => $this->description,

            // Date Range
            'start_date' => $this->start_date?->toISOString(),
            'end_date' => $this->end_date?->toISOString(),

            // Filters
            'location_ids' => $this->location_ids,
            'parameter_filters' => $this->parameter_filters,

            // Report Content
            'summary_statistics' => $this->summary_statistics,
            'trends_analysis' => $this->trends_analysis,
            'charts_data' => $this->charts_data,
            'conclusions' => $this->conclusions,
            'recommendations' => $this->recommendations,

            // Files
            'pdf_path' => $this->pdf_path,
            'excel_path' => $this->excel_path,

            'generated_by' => $this->whenLoaded('generator'),
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Computed
            'download_urls' => [
                'pdf' => $this->pdf_path ? url('storage/' . $this->pdf_path) : null,
                'excel' => $this->excel_path ? url('storage/' . $this->excel_path) : null,
            ],
        ];
    }
}
