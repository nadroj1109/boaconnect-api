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
        Schema::create('user_signing_certificates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Public certificate file only (.cer / .crt)
            $table->string('certificate_path');

            // Certificate identity
            $table->string('subject_dn');
            $table->string('issuer_dn');
            $table->string('serial_number');
            $table->string('fingerprint_sha256', 64)->unique();

            // Certificate validity
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();

            // pending, active, rejected, revoked
            $table->string('status', 20)->default('pending');

            // Administrative approval
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('verified_at')->nullable();

            $table->string('rejection_reason')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_signing_certificates');
    }
};
