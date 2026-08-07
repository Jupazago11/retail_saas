<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('replaces_sale_id')->nullable()->after('cancelled_at')->constrained('sales')->nullOnDelete();
            $table->index(['company_id', 'replaces_sale_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'replaces_sale_id']);
            $table->dropConstrainedForeignId('replaces_sale_id');
        });
    }
};
