<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            // Mismo esquema que dining_tables.size: porcentaje 0-100 del
            // mismo viewBox que pos_x/pos_y.
            $table->decimal('size', 5, 2)->default(6)->after('pos_y');
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn('size');
        });
    }
};
