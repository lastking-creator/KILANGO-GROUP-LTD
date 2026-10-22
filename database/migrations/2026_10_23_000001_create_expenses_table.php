<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date');
            $table->string('category', 60);
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();   // empty = general / head office
            $table->foreignId('user_id')->constrained();
            $table->timestamps();

            $table->index('expense_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
