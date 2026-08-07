<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payable_movements', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->decimal('supplier_credit_after', 14, 2)->default(0)->after('balance_after');
            $table->index(['company_id', 'supplier_id', 'movement_type']);
        });
    }

    public function down(): void
    {
        Schema::table('payable_movements', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'supplier_id', 'movement_type']);
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('supplier_credit_after');
        });
    }
};
