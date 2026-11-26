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
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique();

            $table->foreignId('barangay_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('resident_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('requested_for_resident_id')
                ->constrained('residents')
                ->cascadeOnDelete();

            $table->foreignId('document_type_id')
                ->constrained()
                ->restrictOnDelete();

            $table->text('purpose');

            $table->text('remarks')->nullable();

            $table->string('status', 30);

            $table->timestamp('requested_at');

            $table->timestamp('approved_at')->nullable();

            $table->timestamp('generated_at')->nullable();

            $table->timestamp('released_at')->nullable();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('released_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'status',
                'requested_at',
            ]);

            $table->index([
                'resident_id',
                'requested_for_resident_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
