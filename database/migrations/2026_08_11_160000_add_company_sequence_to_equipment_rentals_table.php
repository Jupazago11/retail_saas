<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_rentals', function (Blueprint $table) {
            $table->unsignedInteger('company_sequence')->nullable()->after('equipment_type');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_rentals', function (Blueprint $table) {
            $table->dropColumn('company_sequence');
        });
    }
};
