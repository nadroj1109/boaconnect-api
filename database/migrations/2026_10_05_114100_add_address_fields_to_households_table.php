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
            $table->integer('region_id')
                ->nullable()
                ->after('barangay_id');

            $table->integer('province_id')
                ->nullable()
                ->after('region_id');

            $table->integer('city_id')
                ->nullable()
                ->after('province_id');

            $table->string('zip_code', 10)
                ->nullable()
                ->after('city_id');

            $table->string('house_no', 100)
                ->nullable()
                ->after('zip_code');

            $table->string('street_name', 150)
                ->nullable()
                ->after('house_no');

            $table->string('subdivision', 150)
                ->nullable()
                ->after('street_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn([
                'region_id',
                'province_id',
                'city_id',
                'zip_code',
                'house_no',
                'street_name',
                'subdivision',
            ]);
        });
    }
};
