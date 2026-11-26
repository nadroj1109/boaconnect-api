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
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_no')
                ->unique();

            $table->foreignId('barangay_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
             * Resident the complaint belongs to.
             */
            $table->foreignId('resident_id')
                ->constrained('residents')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
             * User who actually submitted/encoded
             * the complaint.
             */
            $table->foreignId('submitted_by')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->enum('source', [
                'resident',
                'staff',
            ])->default('resident');

            $table->foreignId('complaint_type_id')
                ->constrained('complaint_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('subject');

            $table->text('description');

            $table->date('incident_date');

            $table->time('incident_time')
                ->nullable();

            $table->string('incident_location')
                ->nullable();

            $table->enum('status', [
                'submitted',
                'under_review',
                'assigned',
                'scheduled',
                'hearing',
                'resolved',
                'closed',
                'rejected',
                'cancelled',
            ])->default('submitted');

            /*
             * Staff/User assigned to handle
             * the complaint.
             */
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->text('resolution')
                ->nullable();

            $table->timestamp('resolved_at')
                ->nullable();

            $table->timestamp('closed_at')
                ->nullable();
            $table->timestamps();

            $table->softDeletes();

            $table->index([
                'barangay_id',
                'status',
            ]);

            $table->index([
                'resident_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
