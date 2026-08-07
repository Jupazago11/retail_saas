<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('credit_account_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->timestamp('credit_due_at')->nullable()->after('sold_at');

            $table->index(['company_id', 'credit_account_id']);
            $table->index(['company_id', 'credit_due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'credit_account_id']);
            $table->dropIndex(['company_id', 'credit_due_at']);
            $table->dropConstrainedForeignId('credit_account_id');
            $table->dropColumn('credit_due_at');
        });
    }
};
