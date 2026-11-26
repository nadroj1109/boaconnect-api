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
        Schema::create('prenatal_visits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('prenatal_record_id')
                ->constrained()
                ->cascadeOnDelete();

            // Scheduled visit number: 1 to 8.
            // Nullable to allow additional/unscheduled consultations.
            $table->unsignedTinyInteger('visit_number')->nullable();

            $table->date('visit_date');

            // Age of pregnancy
            $table->unsignedTinyInteger('aog_weeks')->nullable();
            $table->unsignedTinyInteger('aog_days')->nullable();

            $table->string('blood_pressure')->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->decimal('fundal_height', 6, 2)->nullable();
            $table->string('fetal_heart_beat')->nullable();

            $table->string('presentation')->nullable();
            $table->text('medicine_given')->nullable();
            $table->text('danger_signs')->nullable();

            // Screening and laboratory results
            $table->string('hbsag')->nullable();
            $table->string('hiv_screening')->nullable();
            $table->string('gestational_diabetes')->nullable();
            $table->text('cbc_hgb_hct')->nullable();
            $table->string('rpr_rdt')->nullable();

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

            $table->unique(
                ['prenatal_record_id', 'visit_number'],
                'prenatal_record_visit_number_unique'
            );

            $table->index('visit_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prenatal_visits');
    }
};
