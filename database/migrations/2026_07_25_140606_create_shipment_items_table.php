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
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('delivered_quantity')->default(0);
            $table->timestamps();
            $table->unique(['shipment_id', 'reservation_id']);
            $table->index(['reservation_id', 'created_at']);
        });

        DB::statement('ALTER TABLE shipment_items ADD CONSTRAINT shipment_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE shipment_items ADD CONSTRAINT shipment_items_delivered_quantity_valid CHECK (delivered_quantity <= quantity)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
    }
};
