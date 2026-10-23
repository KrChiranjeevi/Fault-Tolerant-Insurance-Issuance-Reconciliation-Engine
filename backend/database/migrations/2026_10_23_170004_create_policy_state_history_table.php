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
        Schema::create('policy_state_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')
                ->constrained('policy_requests')
                ->cascadeOnDelete();

            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->index();
            $table->string('trigger_event', 64)->nullable();

            $table->string('actor_type', 32)->default('SYSTEM'); // SYSTEM, AGENT, PROVIDER
            $table->string('actor_id', 64)->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['policy_request_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_state_history');
    }
};
