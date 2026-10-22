<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 40)->unique();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('godown_id')->constrained();
            $table->foreignId('received_by')->constrained('users');
            $table->date('received_date');
            $table->string('reference', 100)->nullable();      // supplier invoice or delivery note number
            $table->string('notes')->nullable();
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->timestamps();

            $table->index('received_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
