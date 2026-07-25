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
        Schema::create('provider_webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('external_event_id');
            $table->enum('event_type', ['shipment.confirmed', 'delivery.confirmed']);
            $table->longText('raw_body');
            $table->timestamp('occurred_at');
            $table->enum('status', ['pending', 'processed', 'ignored_as_stale', 'retryable_failure', 'permanently_failed'])->default('pending');
            $table->string('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_event_id']);
            $table->index(['status', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_webhook_receipts');
    }
};
