<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            // Snapshots inmutables del momento de apertura/cierre: no son
            // catalogos que se consulten o reporten por separado (las
            // denominaciones de billetes/monedas son un conjunto fijo), asi
            // que se guardan como json en vez de tablas propias.
            $table->json('opening_funds')->nullable()->after('opening_amount');
            $table->json('closing_denomination_breakdown')->nullable()->after('closing_counted_amount');
        });
    }

    public function down(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropColumn(['opening_funds', 'closing_denomination_breakdown']);
        });
    }
};
