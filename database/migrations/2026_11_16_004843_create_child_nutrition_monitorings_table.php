<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('child_nutrition_monitorings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('resident_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('barangay_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedSmallInteger('monitoring_year');

            $table->unsignedInteger('sequence_no')->nullable();

            $table->string('caregiver_name')->nullable();

            $table->decimal('birthweight', 5, 2)->nullable();

            $table->boolean('low_birth_weight')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // One annual monitoring record per child.
            $table->unique(
                ['resident_id', 'monitoring_year'],
                'child_nutrition_monitoring_resident_year_unique'
            );

            $table->index(['barangay_id', 'monitoring_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('child_nutrition_monitorings');
    }
};
