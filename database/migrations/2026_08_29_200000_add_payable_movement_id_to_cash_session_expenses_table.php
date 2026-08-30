<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_session_expenses', function (Blueprint $table) {
            $table->foreignId('payable_movement_id')->nullable()->after('cash_session_id')
                ->constrained('payable_movements')->nullOnDelete();
            $table->unique('payable_movement_id');
        });
    }

    public function down(): void
    {
        Schema::table('cash_session_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payable_movement_id');
        });
    }
};
