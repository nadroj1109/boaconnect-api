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
        Schema::create('complaint_hearings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')
                ->constrained('complaints')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->date('hearing_date');

            $table->time('hearing_time');

            $table->string('location');

            $table->enum('status', [
                'scheduled',
                'completed',
                'cancelled',
                'rescheduled',
            ])->default('scheduled');

            $table->text('remarks')
                ->nullable();

            $table->foreignId('scheduled_by')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();

            $table->index([
                'complaint_id',
                'hearing_date',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_hearings');
    }
};
