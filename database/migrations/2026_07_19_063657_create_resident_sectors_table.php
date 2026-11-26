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
        Schema::create('resident_sectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('sector_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->boolean('registered')->nullable();
            $table->string('registration_no')->nullable();
            $table->date('registered_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique([
                'resident_id',
                'sector_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resident_sectors');
    }
};
