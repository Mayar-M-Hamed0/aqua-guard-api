<?php

namespace App\Exports;

use App\Models\WaterSample;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class SamplesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithColumnWidths, ShouldAutoSize
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = WaterSample::query()
            ->with(['location', 'collector', 'verifier']);

        // Apply filters
        if (!empty($this->filters['location_id'])) {
            $query->where('location_id', $this->filters['location_id']);
        }

        if (!empty($this->filters['quality_status'])) {
            $query->where('quality_status', $this->filters['quality_status']);
        }

        if (!empty($this->filters['risk_level'])) {
            $query->where('risk_level', $this->filters['risk_level']);
        }

        if (!empty($this->filters['date_from'])) {
            $query->where('collection_date', '>=', $this->filters['date_from']);
        }

        if (!empty($this->filters['date_to'])) {
            $query->where('collection_date', '<=', $this->filters['date_to']);
        }

        return $query->orderBy('collection_date', 'desc');
    }

    public function headings(): array
    {
        return [
            'Sample Code',
            'Location',
            'Collection Date',
            'Collection Time',
            'Collected By',

            // Physical Parameters
            'Temperature (°C)',
            'Turbidity (NTU)',
            'Color',

            // Chemical Parameters
            'pH',
            'EC (µS/cm)',
            'TDS (mg/L)',
            'TSS (mg/L)',
            'Total Hardness',
            'Calcium (Ca)',
            'Magnesium (Mg)',
            'Sodium (Na)',
            'Potassium (K)',
            'Chloride (Cl)',
            'Sulfate (SO4)',
            'Alkalinity',

            // Oxygen
            'DO (mg/L)',
            'BOD (mg/L)',
            'COD (mg/L)',

            // Nutrients
            'Nitrate (NO3)',
            'Nitrite (NO2)',
            'Ammonia (NH3)',
            'Total Nitrogen',
            'Phosphate (PO4)',
            'Total Phosphorus',

            // Heavy Metals
            'Lead (Pb)',
            'Mercury (Hg)',
            'Arsenic (As)',
            'Cadmium (Cd)',
            'Chromium (Cr)',
            'Copper (Cu)',
            'Iron (Fe)',
            'Manganese (Mn)',
            'Zinc (Zn)',

            // Microbiological
            'Total Coliform',
            'Fecal Coliform',
            'E. Coli',

            // WQI
            'WQI WHO',
            'WQI NSF',
            'WQI CCME',
            'WQI Custom',

            // Status
            'Quality Status',
            'Risk Level',
            'Status',
            'Verified By',
            'Verified At',

            'Notes',
        ];
    }

    public function map($sample): array
    {
        return [
            $sample->sample_code,
            $sample->location->name ?? '',
            $sample->collection_date,
            $sample->collection_time,
            $sample->collector->name ?? '',

            // Physical
            $sample->temperature,
            $sample->turbidity,
            $sample->color,

            // Chemical
            $sample->ph,
            $sample->electrical_conductivity,
            $sample->tds,
            $sample->tss,
            $sample->total_hardness,
            $sample->calcium,
            $sample->magnesium,
            $sample->sodium,
            $sample->potassium,
            $sample->chloride,
            $sample->sulfate,
            $sample->alkalinity,

            // Oxygen
            $sample->dissolved_oxygen,
            $sample->bod,
            $sample->cod,

            // Nutrients
            $sample->nitrate,
            $sample->nitrite,
            $sample->ammonia,
            $sample->total_nitrogen,
            $sample->phosphate,
            $sample->total_phosphorus,

            // Heavy Metals
            $sample->lead,
            $sample->mercury,
            $sample->arsenic,
            $sample->cadmium,
            $sample->chromium,
            $sample->copper,
            $sample->iron,
            $sample->manganese,
            $sample->zinc,

            // Microbiological
            $sample->total_coliform,
            $sample->fecal_coliform,
            $sample->e_coli,

            // WQI
            $sample->wqi_who,
            $sample->wqi_nsf,
            $sample->wqi_ccme,
            $sample->wqi_custom,

            // Status
            $sample->quality_status,
            $sample->risk_level,
            $sample->status,
            $sample->verifier->name ?? '',
            $sample->verified_at,

            $sample->notes,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB']
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,  // Sample Code
            'B' => 25,  // Location
            'C' => 15,  // Date
        ];
    }
}
