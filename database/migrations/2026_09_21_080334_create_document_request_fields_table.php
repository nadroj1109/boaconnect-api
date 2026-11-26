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
        Schema::create('document_request_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('document_type_field_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->text('value')
                ->nullable();

            $table->timestamps();

            $table->unique(
                [
                    'document_request_id',
                    'document_type_field_id',
                ],
                'dr_fields_request_type_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_request_fields');
    }
};
