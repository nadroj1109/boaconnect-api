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
        Schema::table('document_requests', function (Blueprint $table) {
            $table->string('document_no')
                ->nullable()
                ->after('request_no');

            $table->foreignId('document_template_id')
                ->nullable()
                ->after('document_type_id')
                ->constrained(
                    'document_templates'
                )
                ->nullOnDelete();

            $table->foreignId('generated_by')
                ->nullable()
                ->after('generated_at')
                ->constrained(
                    'users'
                )
                ->nullOnDelete();

            $table->timestamp('signed_at')
                ->nullable()
                ->after('generated_at');

            $table->foreignId('signed_by')
                ->nullable()
                ->after('signed_at')
                ->constrained(
                    'users'
                )
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropForeign([
                'document_template_id',
            ]);

            $table->dropForeign([
                'generated_by',
            ]);

            $table->dropForeign([
                'signed_by',
            ]);

            $table->dropColumn([
                'document_no',
                'document_template_id',
                'generated_by',
                'signed_at',
                'signed_by',
            ]);
        });
    }
};
