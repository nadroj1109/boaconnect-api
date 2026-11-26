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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('official_position_id')
                ->nullable()
                ->after('barangay_id')
                ->constrained('official_positions')
                ->nullOnDelete();

            $table->boolean('onboarding_required')
                ->default(false)
                ->after('official_position_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign([
                'official_position_id',
            ]);

            $table->dropColumn([
                'official_position_id',
                'onboarding_required',
            ]);
        });
    }
};
