
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add replacement indexes before dropping the old unique indexes.
        Schema::table('blockchain_anchors', function (Blueprint $table) {
            $table->unique(
                ['document_file_id', 'proof_version', 'network'],
                'blockchain_anchors_document_version_network_unique'
            );

            $table->unique(
                ['document_payment_id', 'proof_version', 'network'],
                'blockchain_anchors_payment_version_network_unique'
            );
        });

        Schema::table('blockchain_anchors', function (Blueprint $table) {
            $table->dropUnique(
                'blockchain_anchors_document_version_unique'
            );

            $table->dropUnique(
                'blockchain_anchors_payment_version_unique'
            );
        });
    }

    public function down(): void
    {
        // Restore the original unique indexes before removing replacements.
        Schema::table('blockchain_anchors', function (Blueprint $table) {
            $table->unique(
                ['document_file_id', 'proof_version'],
                'blockchain_anchors_document_version_unique'
            );

            $table->unique(
                ['document_payment_id', 'proof_version'],
                'blockchain_anchors_payment_version_unique'
            );
        });

        Schema::table('blockchain_anchors', function (Blueprint $table) {
            $table->dropUnique(
                'blockchain_anchors_document_version_network_unique'
            );

            $table->dropUnique(
                'blockchain_anchors_payment_version_network_unique'
            );
        });
    }
};
