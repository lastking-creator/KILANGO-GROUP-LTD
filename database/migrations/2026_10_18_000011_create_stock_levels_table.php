<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->enum('location_type', ['block', 'outlet']);
            $table->unsignedBigInteger('location_id');                 // blocks.id or outlets.id
            $table->decimal('quantity', 14, 3)->default(0);            // in the product's base unit
            $table->timestamps();

            $table->unique(['batch_id', 'location_type', 'location_id']);
            $table->index(['location_type', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
