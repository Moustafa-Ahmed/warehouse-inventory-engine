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
        Schema::create('mock_provider_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_provider_shipment_id')->constrained()->restrictOnDelete();
            $table->string('external_event_id')->unique();
            $table->enum('event_type', ['shipment.confirmed', 'delivery.confirmed']);
            $table->longText('raw_body');
            $table->enum('status', ['pending', 'delivering', 'retry_scheduled', 'acknowledged', 'permanently_failed'])->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('occurred_at');
            $table->timestamp('next_delivery_at');
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedSmallInteger('last_response_status_code')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_delivery_at']);
            $table->index(['mock_provider_shipment_id', 'occurred_at'], 'mock_provider_webhooks_shipment_occurred_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_provider_webhooks');
    }
};
