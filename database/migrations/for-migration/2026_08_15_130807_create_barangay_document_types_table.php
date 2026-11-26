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
        Schema::create('barangay_document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('barangay_id')
                ->constrained()
                ->restrictOnDelete();
            $table->decimal('fee', 10, 2)->default(0);
            $table->boolean('requires_payment')->default(false);
            $table->boolean('allow_online_payment')->default(true);
            $table->boolean('requires_chairperson_signature')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique([
                'barangay_id',
                'document_type_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barangay_document_types');
    }
};
