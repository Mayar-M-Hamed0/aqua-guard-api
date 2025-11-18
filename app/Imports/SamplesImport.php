<?php

namespace App\Imports;

use App\Models\WaterSample;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Carbon\Carbon;

class SamplesImport implements
    ToModel,
    WithHeadingRow,
    WithValidation,
    SkipsOnFailure,
    WithBatchInserts,
    WithChunkReading
{
    use SkipsFailures;

    protected $locationId;

    public function __construct($locationId)
    {
        $this->locationId = $locationId;
    }

    public function model(array $row)
    {
        return new WaterSample([
            'sample_code' => $row['sample_code'] ?? $this->generateSampleCode(),
            'location_id' => $this->locationId,
            'collected_by' => Auth::id() ?? 1,
            'collection_date' => $this->parseDate($row['collection_date']),
            'collection_time' => $row['collection_time'] ?? null,

            // Physical Parameters
            'temperature' => $row['temperature'] ?? null,
            'turbidity' => $row['turbidity'] ?? null,
            'color' => $row['color'] ?? null,
            'odor_threshold' => $row['odor_threshold'] ?? null,

            // Chemical Parameters
            'ph' => $row['ph'] ?? null,
            'electrical_conductivity' => $row['ec'] ?? null,
            'tds' => $row['tds'] ?? null,
            'tss' => $row['tss'] ?? null,
            'total_hardness' => $row['total_hardness'] ?? null,
            'calcium' => $row['calcium'] ?? null,
            'magnesium' => $row['magnesium'] ?? null,
            'sodium' => $row['sodium'] ?? null,
            'potassium' => $row['potassium'] ?? null,
            'chloride' => $row['chloride'] ?? null,
            'sulfate' => $row['sulfate'] ?? null,
            'alkalinity' => $row['alkalinity'] ?? null,

            // Oxygen Parameters
            'dissolved_oxygen' => $row['do'] ?? null,
            'bod' => $row['bod'] ?? null,
            'cod' => $row['cod'] ?? null,

            // Nutrients
            'nitrate' => $row['nitrate'] ?? null,
            'nitrite' => $row['nitrite'] ?? null,
            'ammonia' => $row['ammonia'] ?? null,
            'total_nitrogen' => $row['total_nitrogen'] ?? null,
            'phosphate' => $row['phosphate'] ?? null,
            'total_phosphorus' => $row['total_phosphorus'] ?? null,

            // Heavy Metals
            'lead' => $row['lead'] ?? null,
            'mercury' => $row['mercury'] ?? null,
            'arsenic' => $row['arsenic'] ?? null,
            'cadmium' => $row['cadmium'] ?? null,
            'chromium' => $row['chromium'] ?? null,
            'copper' => $row['copper'] ?? null,
            'iron' => $row['iron'] ?? null,
            'manganese' => $row['manganese'] ?? null,
            'zinc' => $row['zinc'] ?? null,

            // Microbiological
            'total_coliform' => $row['total_coliform'] ?? null,
            'fecal_coliform' => $row['fecal_coliform'] ?? null,
            'e_coli' => $row['e_coli'] ?? null,

            // Lab Info
            'lab_name' => $row['lab_name'] ?? null,
            'notes' => $row['notes'] ?? null,

            'status' => 'pending_analysis',
        ]);
    }

    public function rules(): array
    {
        return [
            'collection_date' => 'required|date',

            // Physical Parameters - Optional but must be numeric if provided
            'temperature' => 'nullable|numeric|between:-10,60',
            'turbidity' => 'nullable|numeric|min:0',

            // Chemical Parameters
            'ph' => 'nullable|numeric|between:0,14',
            'ec' => 'nullable|numeric|min:0',
            'tds' => 'nullable|numeric|min:0',
            'tss' => 'nullable|numeric|min:0',

            // Oxygen
            'do' => 'nullable|numeric|min:0|max:20',
            'bod' => 'nullable|numeric|min:0',
            'cod' => 'nullable|numeric|min:0',

            // Nutrients
            'nitrate' => 'nullable|numeric|min:0',
            'nitrite' => 'nullable|numeric|min:0',
            'ammonia' => 'nullable|numeric|min:0',
            'phosphate' => 'nullable|numeric|min:0',

            // Heavy Metals - in µg/L or mg/L
            'lead' => 'nullable|numeric|min:0',
            'mercury' => 'nullable|numeric|min:0',
            'arsenic' => 'nullable|numeric|min:0',
            'cadmium' => 'nullable|numeric|min:0',

            // Microbiological - Integer CFU/100ml
            'total_coliform' => 'nullable|integer|min:0',
            'fecal_coliform' => 'nullable|integer|min:0',
            'e_coli' => 'nullable|integer|min:0',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'collection_date.required' => 'Collection date is required',
            'ph.between' => 'pH must be between 0 and 14',
            'temperature.between' => 'Temperature must be between -10 and 60°C',
            'do.max' => 'Dissolved Oxygen cannot exceed 20 mg/L',
        ];
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    private function parseDate($date)
    {
        if (is_numeric($date)) {
            // Excel date format
            return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($date));
        }

        return Carbon::parse($date);
    }

    private function generateSampleCode()
    {
        $year = date('Y');
        $lastSample = WaterSample::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $number = $lastSample ? (int)substr($lastSample->sample_code, -3) + 1 : 1;

        return 'WS-' . $year . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
}
