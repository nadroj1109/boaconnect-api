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
        Schema::create('verification_codes', function (Blueprint $table) {
            
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('recipient');

            $table->enum('channel', [
                'email',
                'sms',
            ]);

            $table->string('purpose', 50);

            $table->string('code');

            $table->unsignedTinyInteger('attempts')
                ->default(0);

            $table->uuid('verification_token')
                ->nullable()
                ->unique();

            $table->timestamp('expires_at');

            $table->timestamp('verified_at')
                ->nullable();

            $table->timestamp('consumed_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'recipient',
                'purpose',
            ]);

            $table->index([
                'channel',
                'purpose',
            ]);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};
