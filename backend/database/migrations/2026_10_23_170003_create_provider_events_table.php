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
        Schema::create('provider_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_event_id', 128)->unique();
            $table->foreignId('policy_request_id')
                ->nullable()
                ->constrained('policy_requests')
                ->nullOnDelete();

            $table->string('provider_code', 64)->index();
            $table->string('event_type', 64)->index();
            $table->string('status', 32)->default('RECEIVED')->index(); // RECEIVED, PROCESSED, FAILED, IGNORED

            $table->json('payload');
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_events');
    }
};
