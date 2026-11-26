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
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50)->unique();

            $table->string('name');

            $table->text('description')->nullable();

            $table->decimal('fee', 10, 2)->nullable();

            $table->boolean('requires_payment')->default(false);

            $table->boolean('allow_online_payment')->default(true);

            $table->boolean('requires_chairperson_signature')->default(false);

            $table->string('template')->nullable();

            $table->unsignedInteger('processing_days')->default(1);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
