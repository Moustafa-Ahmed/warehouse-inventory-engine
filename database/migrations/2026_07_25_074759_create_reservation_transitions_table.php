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
        Schema::create('reservation_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();
            $table->foreignId('operation_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->nullable();
            $table->string('reason')->nullable();
            $table->enum('before_kind', ['temporary', 'confirmed']);
            $table->enum('after_kind', ['temporary', 'confirmed']);
            $table->enum('before_status', ['open', 'released', 'expired', 'closed']);
            $table->enum('after_status', ['open', 'released', 'expired', 'closed']);
            $table->unsignedInteger('before_reserved_quantity');
            $table->unsignedInteger('after_reserved_quantity');
            $table->unsignedInteger('before_picked_quantity');
            $table->unsignedInteger('after_picked_quantity');
            $table->unsignedInteger('before_packed_quantity');
            $table->unsignedInteger('after_packed_quantity');
            $table->unsignedInteger('before_shipped_quantity');
            $table->unsignedInteger('after_shipped_quantity');
            $table->unsignedInteger('before_released_quantity');
            $table->unsignedInteger('after_released_quantity');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['reservation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_transitions');
    }
};
