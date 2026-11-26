
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
        Schema::table('blockchain_anchors', function (Blueprint $table) {
            $table->uuid('submission_claim_token')
                ->nullable()
                ->after('transaction_hash');

            $table->timestamp('submission_claimed_at')
                ->nullable()
                ->after('submission_claim_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blockchain_anchors', function (Blueprint $table) {
            $table->dropColumn([
                'submission_claim_token',
                'submission_claimed_at',
            ]);
        });
    }
};
