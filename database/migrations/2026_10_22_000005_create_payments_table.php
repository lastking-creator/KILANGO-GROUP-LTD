<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();   // empty for a later repayment of an account
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('method', ['cash', 'mobile_money', 'card', 'on_account']);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 80)->nullable();                                // mobile money / card transaction reference
            $table->foreignId('user_id')->constrained();
            $table->timestamps();

            $table->index('shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
