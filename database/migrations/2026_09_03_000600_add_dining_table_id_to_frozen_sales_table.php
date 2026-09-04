<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('frozen_sales', function (Blueprint $table) {
            $table->foreignId('dining_table_id')->nullable()->after('cash_register_id')
                ->constrained('dining_tables')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('frozen_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dining_table_id');
        });
    }
};
