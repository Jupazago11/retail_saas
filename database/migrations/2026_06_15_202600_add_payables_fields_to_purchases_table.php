<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('amount_paid', 14, 2)->default(0)->after('returned_from_inventory_at');
            $table->decimal('balance_due', 14, 2)->default(0)->after('amount_paid');
            $table->timestamp('paid_at')->nullable()->after('balance_due');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'balance_due', 'paid_at']);
        });
    }
};
