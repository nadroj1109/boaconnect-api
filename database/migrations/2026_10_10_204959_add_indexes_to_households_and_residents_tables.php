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
        Schema::table('households', function (Blueprint $table) {
            // Supports barangay filtering and household-name sorting.
            $table->index(
                ['barangay_id', 'household_name'],
                'households_barangay_name_idx'
            );

            $table->index(
                'sitio_purok_id',
                'households_sitio_purok_id_idx'
            );
        });

        Schema::table('residents', function (Blueprint $table) {
            $table->index(
                'household_id',
                'residents_household_id_idx'
            );

            $table->index(
                'barangay_id',
                'residents_barangay_id_idx'
            );

            $table->index(
                'ethnicity_id',
                'residents_ethnicity_id_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropIndex('households_barangay_name_idx');
            $table->dropIndex('households_sitio_purok_id_idx');
        });

        Schema::table('residents', function (Blueprint $table) {
            $table->dropIndex('residents_household_id_idx');
            $table->dropIndex('residents_barangay_id_idx');
            $table->dropIndex('residents_ethnicity_id_idx');
        });
    }
};
