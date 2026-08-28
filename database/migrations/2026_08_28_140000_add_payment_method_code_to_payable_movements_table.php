<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payable_movements', function (Blueprint $table) {
            $table->string('payment_method_code')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('payable_movements', function (Blueprint $table) {
            $table->dropColumn('payment_method_code');
        });
    }
};
