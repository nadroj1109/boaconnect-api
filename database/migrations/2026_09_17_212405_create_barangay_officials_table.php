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
        Schema::create('barangay_officials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('resident_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('official_position_id')
                ->constrained('official_positions')
                ->restrictOnDelete();

            $table->date('term_start');

            $table->date('term_end')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'barangay_id',
                'is_active',
            ]);

            $table->index([
                'resident_id',
            ]);

            $table->index([
                'official_position_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barangay_officials');
    }
};
