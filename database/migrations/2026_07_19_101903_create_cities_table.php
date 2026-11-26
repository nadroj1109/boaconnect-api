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
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('province_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('psgc_code', 20)->unique();
            $table->string('city_code', 20)->unique();
            $table->string('city', 150);
            $table->string('zip_code',10)->nullable();
            $table->boolean('is_city')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
