<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The audit trail: every change to stock is written here as well.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('product_batches');
            $table->enum('movement_type', ['receipt', 'transfer_out', 'transfer_in', 'sale', 'sale_return', 'adjustment', 'wastage']);
            $table->enum('location_type', ['block', 'outlet']);
            $table->unsignedBigInteger('location_id');
            $table->decimal('quantity', 14, 3);                // positive = stock in, negative = stock out
            $table->string('reference_type', 30)->nullable();  // goods_receipt, transfer, sale, stock_take
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();

            $table->index(['location_type', 'location_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
