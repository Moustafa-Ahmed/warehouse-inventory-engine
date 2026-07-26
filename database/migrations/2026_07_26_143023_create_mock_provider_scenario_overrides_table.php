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
        Schema::create('mock_provider_scenario_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_reference')->unique();
            $table->enum('scenario', ['immediate_success', 'delayed_success', 'permanent_failure', 'timeout_then_success', 'success_with_duplicate_delivery', 'out_of_order_delivery']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_provider_scenario_overrides');
    }
};
