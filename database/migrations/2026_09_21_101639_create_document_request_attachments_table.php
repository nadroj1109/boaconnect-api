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
        Schema::create('document_request_attachments', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')
                ->unique();

            $table->foreignId(
                'document_request_id'
            );

            $table->foreign(
                'document_request_id',
                'dr_attach_request_fk'
            )
                ->references('id')
                ->on('document_requests')
                ->cascadeOnDelete();

            $table->foreignId(
                'document_type_requirement_id'
            );

            $table->foreign(
                'document_type_requirement_id',
                'dr_attach_type_req_fk'
            )
                ->references('id')
                ->on('document_type_requirements')
                ->restrictOnDelete();

            $table->string('filename');

            $table->string('path');

            $table->string('mime_type');

            $table->unsignedBigInteger('size');

            $table->foreignId(
                'uploaded_by'
            );

            $table->foreign(
                'uploaded_by',
                'dr_attach_uploaded_by_fk'
            )
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                [
                    'document_request_id',
                    'document_type_requirement_id',
                ],
                'dr_attach_request_req_unique'
            );

            $table->index(
                'document_request_id'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_request_attachments');
    }
};
