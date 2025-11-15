<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_samples', function (Blueprint $table) {
            $table->id();
            $table->string('sample_code')->unique(); // e.g., WS-2024-001
            $table->foreignId('location_id')->constrained('monitoring_locations');
            $table->foreignId('collected_by')->constrained('users');
            $table->dateTime('collection_date');
            $table->time('collection_time')->nullable();

            // Physical Parameters
            $table->decimal('temperature', 5, 2)->nullable(); // °C
            $table->decimal('turbidity', 8, 2)->nullable(); // NTU
            $table->string('color')->nullable(); // Hazen units or description
            $table->decimal('odor_threshold', 5, 2)->nullable();

            // Chemical Parameters - Basic
            $table->decimal('ph', 4, 2)->nullable(); // 0-14
            $table->decimal('electrical_conductivity', 10, 2)->nullable(); // EC µS/cm
            $table->decimal('tds', 10, 2)->nullable(); // Total Dissolved Solids mg/L
            $table->decimal('tss', 10, 2)->nullable(); // Total Suspended Solids mg/L
            $table->decimal('total_hardness', 10, 2)->nullable(); // mg/L as CaCO3
            $table->decimal('calcium', 10, 2)->nullable(); // Ca mg/L
            $table->decimal('magnesium', 10, 2)->nullable(); // Mg mg/L
            $table->decimal('sodium', 10, 2)->nullable(); // Na mg/L
            $table->decimal('potassium', 10, 2)->nullable(); // K mg/L
            $table->decimal('chloride', 10, 2)->nullable(); // Cl mg/L
            $table->decimal('sulfate', 10, 2)->nullable(); // SO4 mg/L
            $table->decimal('alkalinity', 10, 2)->nullable(); // mg/L as CaCO3

            // Oxygen Parameters
            $table->decimal('dissolved_oxygen', 8, 2)->nullable(); // DO mg/L
            $table->decimal('bod', 8, 2)->nullable(); // Biochemical Oxygen Demand mg/L
            $table->decimal('cod', 8, 2)->nullable(); // Chemical Oxygen Demand mg/L

            // Nutrients
            $table->decimal('nitrate', 8, 2)->nullable(); // NO3 mg/L
            $table->decimal('nitrite', 8, 2)->nullable(); // NO2 mg/L
            $table->decimal('ammonia', 8, 2)->nullable(); // NH3 mg/L
            $table->decimal('total_nitrogen', 8, 2)->nullable(); // TN mg/L
            $table->decimal('phosphate', 8, 2)->nullable(); // PO4 mg/L
            $table->decimal('total_phosphorus', 8, 2)->nullable(); // TP mg/L

            // Heavy Metals (µg/L or mg/L)
            $table->decimal('lead', 10, 4)->nullable(); // Pb
            $table->decimal('mercury', 10, 4)->nullable(); // Hg
            $table->decimal('arsenic', 10, 4)->nullable(); // As
            $table->decimal('cadmium', 10, 4)->nullable(); // Cd
            $table->decimal('chromium', 10, 4)->nullable(); // Cr
            $table->decimal('copper', 10, 4)->nullable(); // Cu
            $table->decimal('iron', 10, 4)->nullable(); // Fe
            $table->decimal('manganese', 10, 4)->nullable(); // Mn
            $table->decimal('zinc', 10, 4)->nullable(); // Zn

            // Microbiological
            $table->integer('total_coliform')->nullable(); // CFU/100ml
            $table->integer('fecal_coliform')->nullable(); // CFU/100ml
            $table->integer('e_coli')->nullable(); // CFU/100ml

            // WQI Calculations (Auto-calculated)
            $table->decimal('wqi_who', 5, 2)->nullable(); // WHO Standard
            $table->decimal('wqi_nsf', 5, 2)->nullable(); // NSF WQI
            $table->decimal('wqi_ccme', 5, 2)->nullable(); // Canadian WQI
            $table->decimal('wqi_custom', 5, 2)->nullable(); // Egyptian Custom

            // Classification (Auto-assigned based on WQI)
            $table->enum('quality_status', [
                'excellent',    // 90-100
                'good',         // 70-89
                'fair',         // 50-69
                'poor',         // 25-49
                'very_poor'     // 0-24
            ])->nullable();

            // AI Predictions
            $table->json('ai_predictions')->nullable(); // AI model results
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->text('ai_recommendations')->nullable();

            // Risk Assessment
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->json('risk_factors')->nullable(); // Array of detected risks

            // Lab & Documentation
            $table->string('lab_name')->nullable();
            $table->string('lab_certificate')->nullable();
            $table->text('notes')->nullable();
            $table->json('attachments')->nullable(); // Lab reports, photos

            // Status & Workflow
            $table->enum('status', [
                'pending_analysis',
                'analyzed',
                'verified',
                'flagged',
                'archived'
            ])->default('pending_analysis');

            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->dateTime('verified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index('collection_date');
            $table->index('location_id');
            $table->index('quality_status');
            $table->index('risk_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_samples');
    }
};
