<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // WQI Standards Configuration
        Schema::create('wqi_standards', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // WHO, NSF, CCME, Egyptian
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->enum('water_type', ['drinking', 'irrigation', 'industrial', 'recreational']);
            $table->json('parameters_config'); // Which parameters to use
            $table->json('calculation_method'); // Formula/weights
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Parameter Standards (Acceptable ranges)
        Schema::create('parameter_standards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standard_id')->constrained('wqi_standards');
            $table->string('parameter_name'); // ph, tds, etc.
            $table->decimal('ideal_value', 10, 4)->nullable();
            $table->decimal('min_acceptable', 10, 4)->nullable();
            $table->decimal('max_acceptable', 10, 4)->nullable();
            $table->decimal('min_permissible', 10, 4)->nullable();
            $table->decimal('max_permissible', 10, 4)->nullable();
            $table->string('unit'); // mg/L, NTU, etc.
            $table->integer('weight')->default(1); // Importance weight
            $table->text('health_impact')->nullable();
            $table->timestamps();

            $table->unique(['standard_id', 'parameter_name']);
        });

        // Alerts & Notifications
        Schema::create('water_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained('water_samples');
            $table->foreignId('location_id')->constrained('monitoring_locations');
            $table->enum('severity', ['info', 'warning', 'critical', 'emergency']);
            $table->string('alert_type'); // high_contamination, parameter_exceeded, etc.
            $table->string('parameter_name')->nullable();
            $table->decimal('parameter_value', 10, 4)->nullable();
            $table->decimal('threshold_value', 10, 4)->nullable();
            $table->text('message');
            $table->json('affected_parameters')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['severity', 'is_resolved']);
        });

        // Analysis Reports
        Schema::create('analysis_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_code')->unique();
            $table->enum('report_type', [
                'single_sample',
                'location_trend',
                'comparative',
                'regional',
                'custom'
            ]);
            $table->string('title');
            $table->text('description')->nullable();

            // Date Range
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Filters
            $table->json('location_ids')->nullable();
            $table->json('parameter_filters')->nullable();

            // Report Content
            $table->json('summary_statistics')->nullable();
            $table->json('trends_analysis')->nullable();
            $table->json('charts_data')->nullable();
            $table->text('conclusions')->nullable();
            $table->text('recommendations')->nullable();

            // Files
            $table->string('pdf_path')->nullable();
            $table->string('excel_path')->nullable();

            $table->foreignId('generated_by')->constrained('users');
            $table->enum('status', ['generating', 'completed', 'failed'])->default('generating');
            $table->timestamps();
        });

        // WQI Trends History (for time-series analysis)
        Schema::create('wqi_trends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('monitoring_locations');
            $table->date('date');
            $table->decimal('avg_wqi', 5, 2);
            $table->decimal('min_wqi', 5, 2);
            $table->decimal('max_wqi', 5, 2);
            $table->integer('sample_count');
            $table->enum('trend_direction', ['improving', 'stable', 'declining'])->nullable();
            $table->json('parameter_averages')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wqi_trends');
        Schema::dropIfExists('analysis_reports');
        Schema::dropIfExists('water_alerts');
        Schema::dropIfExists('parameter_standards');
        Schema::dropIfExists('wqi_standards');
    }
};
