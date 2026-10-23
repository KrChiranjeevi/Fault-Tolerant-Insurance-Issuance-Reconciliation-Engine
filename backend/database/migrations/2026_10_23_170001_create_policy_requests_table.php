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
        Schema::create('policy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number', 64)->nullable()->unique();
            $table->string('agent_id', 64)->index();
            $table->string('client_request_id', 128);
            
            // Core status
            $table->string('status', 32)->default('QUOTED')->index();

            // Insured details
            $table->string('insured_name');
            $table->string('insured_email')->index();
            $table->date('date_of_birth');
            $table->unsignedInteger('traveler_age');

            // Trip details
            $table->string('destination', 64)->index();
            $table->date('trip_start')->index();
            $table->date('trip_end');
            $table->unsignedInteger('trip_days');

            // Plan & Coverage
            $table->string('plan', 64)->index();
            $table->string('coverage_type', 64);

            // Pricing snapshot
            $table->char('currency', 3)->default('USD');
            $table->decimal('premium_amount', 12, 2);
            $table->json('premium_snapshot');

            $table->json('metadata')->nullable();
            $table->timestamps();

            // Idempotency: agent cannot submit duplicate client_request_id
            $table->unique(['agent_id', 'client_request_id'], 'uq_policy_agent_client_req');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_requests');
    }
};
