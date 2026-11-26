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
        Schema::table('resident_household_registrations', function (Blueprint $table) {
            $table->string('status', 20)
                ->default('pending')
                ->after('household_details');

            $table->text('rejection_reason')
                ->nullable()
                ->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resident_household_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'rejection_reason',
            ]);
        });
    }
};
