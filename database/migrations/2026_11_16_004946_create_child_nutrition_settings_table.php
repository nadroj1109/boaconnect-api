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
        Schema::create('child_nutrition_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('age_restriction_enabled')->default(false);

            $table->unsignedSmallInteger('minimum_age_months')->nullable();
            $table->unsignedSmallInteger('maximum_age_months')->nullable();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('child_nutrition_settings');
    }
};
