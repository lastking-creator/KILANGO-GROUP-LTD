<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable();
            $table->enum('role', ['admin', 'godown_keeper', 'outlet_manager', 'cashier'])->default('cashier');
            $table->foreignId('godown_id')->nullable()->constrained()->nullOnDelete();   // godown keepers
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();   // managers and cashiers
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('godown_id');
            $table->dropConstrainedForeignId('outlet_id');
            $table->dropColumn(['phone', 'role', 'is_active']);
        });
    }
};
