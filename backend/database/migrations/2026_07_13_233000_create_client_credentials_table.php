<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('subject_name');
            $table->string('holder_cnpj', 14);
            $table->string('fingerprint_sha256', 64);
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to');
            $table->string('vault_object_id', 26);
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->boolean('expires_alert_30')->default(false);
            $table->boolean('expires_alert_7')->default(false);
            $table->boolean('expires_alert_1')->default(false);
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['office_id', 'valid_to']);
            $table->unique(['client_id', 'fingerprint_sha256', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_credentials');
    }
};
