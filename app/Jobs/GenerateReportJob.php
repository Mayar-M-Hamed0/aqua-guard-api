<?php
namespace App\Jobs;

use App\Models\AnalysisReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $reportId;
    public $requestData;

    public function __construct($reportId, array $requestData)
    {
        $this->reportId = $reportId;
        $this->requestData = $requestData;
    }

    public function handle()
    {
        $report = AnalysisReport::find($this->reportId);
        if (!$report) return;
            $generator = app()->make('App\Services\ReportGeneratorService');

            $reportData = $generator->generate($report, $this->requestData);

            $report->update([
                'summary_statistics' => $reportData['summary'],
                'trends_analysis' => $reportData['trends'],
                'charts_data' => $reportData['charts'],
                'conclusions' => $reportData['conclusions'],
                'recommendations' => $reportData['recommendations'],
                'pdf_path' => $reportData['pdf_path'] ?? null,
                'excel_path' => $reportData['excel_path'] ?? null,
                'status' => 'completed',
            ]);
    }
}
