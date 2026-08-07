<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('posted_to_inventory_at');
            $table->timestamp('returned_at')->nullable()->after('cancelled_at');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('returned_quantity', 14, 6)->default(0)->after('quantity');
            $table->decimal('returned_base_quantity', 14, 6)->default(0)->after('returned_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['returned_quantity', 'returned_base_quantity']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'returned_at']);
        });
    }
};
