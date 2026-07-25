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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('ordered_quantity');
            $table->unsignedInteger('cancelled_quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('picked_quantity')->default(0);
            $table->unsignedInteger('packed_quantity')->default(0);
            $table->unsignedInteger('shipped_quantity')->default(0);
            $table->unsignedInteger('delivered_quantity')->default(0);
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
            $table->index(['product_id', 'created_at']);
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_ordered_quantity_positive CHECK (ordered_quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_conservation CHECK (cancelled_quantity + reserved_quantity + picked_quantity + packed_quantity + shipped_quantity <= ordered_quantity)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_delivered_quantity_valid CHECK (delivered_quantity <= shipped_quantity)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
