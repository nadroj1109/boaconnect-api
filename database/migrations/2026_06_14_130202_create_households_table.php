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
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->integer('barangay_id');
            $table->integer('sitio_purok_id');
            $table->string('sitio_purok', 50)->nullable();
            $table->string('complete_address', 150)->nullable();
            $table->smallInteger('no_of_family')->default(0);
            $table->smallInteger('no_of_hh_member')->default(0);
            $table->smallInteger('no_of_migrant')->default(0);
            $table->string('household_no', 50)->unique();
            $table->string('household_type', 50);
            $table->string('household_type_other', 100)->nullable();
            $table->string('tenure_status', 50);
            $table->string('tenure_status_other', 50)->nullable();
            $table->string('household_unit', 50);
            $table->string('household_unit_other', 50)->nullable();
            $table->string('household_name', 150)->nullable();
            $table->decimal('monthly_income', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
