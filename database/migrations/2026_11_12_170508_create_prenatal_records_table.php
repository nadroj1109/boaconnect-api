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
        Schema::create('prenatal_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('resident_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('barangay_id')
                ->constrained()
                ->restrictOnDelete();

            $table->date('registration_date');

            $table->string('spouse_name')->nullable();
            $table->string('ob_score')->nullable();

            $table->date('lmp')->nullable();
            $table->date('edc')->nullable();

            // Registration measurements
            $table->decimal('height', 6, 2)->nullable();
            $table->decimal('initial_weight', 6, 2)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();

            $table->text('past_medical_history')->nullable();
            $table->text('family_history')->nullable();

            // Tetanus toxoid immunization dates
            $table->date('tt1_date')->nullable();
            $table->date('tt2_date')->nullable();
            $table->date('tt3_date')->nullable();
            $table->date('tt4_date')->nullable();
            $table->date('tt5_date')->nullable();

            $table->string('pregnancy_outcome')->nullable();
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

            $table->index(['barangay_id', 'registration_date']);
            $table->index(['resident_id', 'registration_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prenatal_records');
    }
};
