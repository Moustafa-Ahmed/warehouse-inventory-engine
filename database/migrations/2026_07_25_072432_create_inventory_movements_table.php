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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->enum('source_bucket', ['available', 'reserved', 'picked', 'packed', 'shipped'])->nullable();
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->enum('destination_bucket', ['available', 'reserved', 'picked', 'packed', 'shipped'])->nullable();
            $table->unsignedInteger('quantity');
            $table->string('business_reference_type');
            $table->string('business_reference_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_reference_type', 'business_reference_id'], 'inventory_movements_business_reference_index');
            $table->index(['source_warehouse_id', 'created_at']);
            $table->index(['destination_warehouse_id', 'created_at']);
        });

        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_quantity_positive CHECK (quantity > 0)');
        DB::statement(
            "ALTER TABLE inventory_movements
            ADD CONSTRAINT inventory_movements_source_endpoint_valid
            CHECK (
                (source_warehouse_id IS NULL AND source_bucket IS NULL)
                OR (
                    source_warehouse_id IS NOT NULL
                    AND source_bucket IS NOT NULL
                    AND source_bucket IN ('available', 'reserved', 'picked', 'packed')
                )
            )"
        );
        DB::statement(
            "ALTER TABLE inventory_movements
            ADD CONSTRAINT inventory_movements_destination_endpoint_valid
            CHECK (
                (destination_warehouse_id IS NULL AND destination_bucket IS NULL)
                OR (
                    destination_warehouse_id IS NOT NULL
                    AND destination_bucket IS NOT NULL
                    AND destination_bucket IN ('available', 'reserved', 'picked', 'packed')
                )
                OR (
                    destination_warehouse_id IS NULL
                    AND destination_bucket = 'shipped'
                )
            )"
        );
        DB::statement(
            'ALTER TABLE inventory_movements
            ADD CONSTRAINT inventory_movements_endpoint_required
            CHECK (
                source_warehouse_id IS NOT NULL
                OR destination_warehouse_id IS NOT NULL
            )'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
