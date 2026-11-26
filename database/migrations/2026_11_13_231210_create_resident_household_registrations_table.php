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
        Schema::create('resident_household_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('requested_household_id')
                ->nullable()
                ->constrained('households')
                ->nullOnDelete();

            $table->json('household_details')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resident_household_registrations');
    }
};
