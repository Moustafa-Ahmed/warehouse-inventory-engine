<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['pending_handoff', 'shipped'])->default('pending_handoff');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['order_id', 'created_at']);
            $table->index(['warehouse_id', 'created_at']);
        });

        DB::statement(
            "ALTER TABLE shipments
            ADD CONSTRAINT shipments_status_shipped_at_consistent
            CHECK (
                (status = 'pending_handoff' AND shipped_at IS NULL)
                OR (status = 'shipped' AND shipped_at IS NOT NULL)
            )"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
