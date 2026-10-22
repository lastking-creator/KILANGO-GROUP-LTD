<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->decimal('capacity', 14, 3)->nullable();
            $table->string('capacity_unit', 20)->nullable();   // label such as bags, cartons, kg
        });
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->dropColumn(['capacity', 'capacity_unit']);
        });
    }
};
