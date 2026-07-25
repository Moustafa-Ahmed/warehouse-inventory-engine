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
        Schema::create('mock_provider_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('provider_request_key')->unique();
            $table->string('external_shipment_id')->nullable()->unique();
            $table->string('shipment_reference');
            $table->enum('scenario', ['immediate_success', 'delayed_success', 'permanent_failure', 'timeout_then_success', 'success_with_duplicate_delivery', 'out_of_order_delivery']);
            $table->boolean('scenario_was_forced')->default(false);
            $table->enum('status', ['accepted', 'permanently_rejected', 'handoff_confirmed', 'delivered'])->default('accepted');
            $table->string('failure_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('handoff_confirmed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
            $table->index(['shipment_reference', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_provider_shipments');
    }
};
