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
        Schema::create('provider_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->restrictOnDelete();
            $table->string('provider_request_key')->unique();
            $table->enum('status', ['pending', 'accepted', 'unknown', 'permanently_failed'])->default('pending');
            $table->string('external_shipment_id')->nullable()->unique();
            $table->string('failure_reason')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
            $table->index(['shipment_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_submissions');
    }
};
