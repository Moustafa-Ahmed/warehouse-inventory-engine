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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->enum('kind', ['temporary', 'confirmed']);
            $table->enum('status', ['open', 'released', 'expired', 'closed'])->default('open');
            $table->unsignedInteger('requested_quantity');
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('picked_quantity')->default(0);
            $table->unsignedInteger('packed_quantity')->default(0);
            $table->unsignedInteger('shipped_quantity')->default(0);
            $table->unsignedInteger('released_quantity')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['kind', 'status', 'expires_at']);
            $table->index(['order_item_id', 'created_at']);
        });

        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_requested_quantity_positive CHECK (requested_quantity > 0)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_quantity_conservation CHECK (reserved_quantity + picked_quantity + packed_quantity + shipped_quantity + released_quantity <= requested_quantity)');
        DB::unprepared("CREATE TRIGGER reservations_requested_quantity_immutable BEFORE UPDATE ON reservations FOR EACH ROW BEGIN IF NEW.requested_quantity <> OLD.requested_quantity THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'requested_quantity is immutable'; END IF; END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS reservations_requested_quantity_immutable');
        Schema::dropIfExists('reservations');
    }
};
