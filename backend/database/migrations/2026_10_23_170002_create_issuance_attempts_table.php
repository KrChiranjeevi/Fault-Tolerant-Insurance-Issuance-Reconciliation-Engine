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
        Schema::create('issuance_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')
                ->constrained('policy_requests')
                ->cascadeOnDelete();

            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('provider_code', 64)->index();
            $table->string('status', 32)->index(); // INITIATED, SUCCESS, FAILED, TIMED_OUT

            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();

            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamps();

            $table->index(['policy_request_id', 'attempt_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issuance_attempts');
    }
};
