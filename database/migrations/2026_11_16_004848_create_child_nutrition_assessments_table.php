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
        Schema::create('child_nutrition_assessments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('child_nutrition_monitoring_id');

            $table->foreign('child_nutrition_monitoring_id', 'cn_assess_monitoring_fk')
                ->references('id')
                ->on('child_nutrition_monitorings')
                ->cascadeOnDelete();

            // 1 = January, 12 = December
            $table->unsignedTinyInteger('assessment_month');

            $table->date('assessment_date');

            $table->unsignedSmallInteger('age_months')->nullable();

            $table->decimal('weight', 6, 2)->nullable();
            $table->decimal('height', 6, 2)->nullable();
            $table->decimal('muac', 5, 2)->nullable();

            // Manually recorded edema observation.
            $table->string('edema', 50)->nullable();

            // Manually entered nutritional classifications.
            $table->string('weight_for_age')->nullable();
            $table->string('height_for_age')->nullable();
            $table->string('weight_for_height')->nullable();
            $table->string('muac_edema_status')->nullable();

            $table->text('remarks')->nullable();

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

            // Only one assessment per month for each annual record.
            $table->unique(
                ['child_nutrition_monitoring_id', 'assessment_month'],
                'child_nutrition_assessment_month_unique'
            );

            $table->index('assessment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('child_nutrition_assessments');
    }
};
