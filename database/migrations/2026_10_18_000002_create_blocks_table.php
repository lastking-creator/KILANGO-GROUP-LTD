<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('godown_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);                       // A, B, C, D, E
            $table->string('name', 100)->nullable();
            $table->enum('storage_type', ['dry', 'cold', 'freezer'])->default('dry');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['godown_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
