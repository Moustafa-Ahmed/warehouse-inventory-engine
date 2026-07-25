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
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation_type')->index();
            $table->string('idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->string('status')->default('pending')->index();
            $table->string('result_reference')->nullable();
            $table->json('result_payload')->nullable();
            $table->json('failure_context')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};
