<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // e.g., LOC-001
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('address')->nullable();
            $table->enum('type', [
                'river',
                'lake',
                'groundwater',
                'sea',
                'reservoir',
                'treatment_plant',
                'distribution_network'
            ]);
            $table->string('governorate')->nullable(); // محافظة
            $table->string('city')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Extra info
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['latitude', 'longitude']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_locations');
    }
};
