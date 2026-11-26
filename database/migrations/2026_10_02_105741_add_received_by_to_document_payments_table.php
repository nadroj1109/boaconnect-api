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
        Schema::table('document_payments', function (Blueprint $table) {
            $table->foreignId('received_by')
                ->nullable()
                ->after('reference_no')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('received_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_payments', function (Blueprint $table) {
            $table->dropForeign(['received_by']);
            $table->dropIndex(['received_by']);
            $table->dropColumn('received_by');
        });
    }
};
