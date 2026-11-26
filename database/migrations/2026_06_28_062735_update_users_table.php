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
        Schema::table('users', function (Blueprint $table) {

            // Rename Laravel's default name column
            $table->renameColumn('name', 'display_name');

            // Relationship to resident
            $table->foreignId('resident_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            // Optional contact number for non-resident users
            $table->string('mobile_number', 20)
                ->nullable()
                ->after('email');

            // Account status
            $table->boolean('is_active')
                ->default(true)
                ->after('password');

            // Barangay
            $table->integer('barangay_id')->nullable()->after('remember_token');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropForeign(['resident_id']);
            $table->dropColumn([
                'resident_id',
                'mobile_number',
                'is_active',
            ]);

            $table->renameColumn('display_name', 'name');
        });
    }
};
