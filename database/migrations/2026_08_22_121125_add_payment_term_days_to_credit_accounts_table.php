<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_accounts', function (Blueprint $table) {
            // Null = usa credit.default_term_days de la empresa. Mismo patron
            // que suppliers.payment_term_days: plazo propio por cuenta, sin
            // forzar que toda la cartera comparta el mismo vencimiento.
            $table->unsignedInteger('payment_term_days')->nullable()->after('balance_due');
        });
    }

    public function down(): void
    {
        Schema::table('credit_accounts', function (Blueprint $table) {
            $table->dropColumn('payment_term_days');
        });
    }
};
