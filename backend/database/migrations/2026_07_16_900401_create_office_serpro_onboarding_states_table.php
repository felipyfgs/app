<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job-facing stub for onboarding state machine (F-3.1).
 * A-1.1 may later expand/backfill; unique (office_id, environment) is stable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('office_serpro_onboarding_states')) {
            return;
        }

        Schema::create('office_serpro_onboarding_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete();
            $table->string('environment', 32);
            $table->string('status', 32)->default('incomplete');
            $table->string('idempotency_key', 64)->nullable();
            $table->string('last_step', 64)->nullable();
            $table->string('actionable_code', 64)->nullable();
            $table->string('actionable_message', 500)->nullable();
            $table->string('technical_code', 64)->nullable();
            $table->string('technical_message', 500)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('provisioning_started_at')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('last_transition_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['office_id', 'environment'], 'office_serpro_onboarding_office_env_uq');
            $table->index(['status', 'environment'], 'office_serpro_onboarding_status_env_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_serpro_onboarding_states');
    }
};
