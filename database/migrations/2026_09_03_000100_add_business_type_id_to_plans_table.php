<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->foreignId('business_type_id')->nullable()->after('id')
                ->constrained('business_types')->restrictOnDelete();
        });

        $generalId = DB::table('business_types')->where('code', 'general')->value('id');

        DB::table('plans')->whereNull('business_type_id')->update([
            'business_type_id' => $generalId,
        ]);

        // business_type_id se deja nullable a nivel de esquema (igual que
        // companies.business_type_id) para no depender de ALTER COLUMN crudo,
        // que solo corre en Postgres y revienta la suite de tests en SQLite.
        // PlanCatalogBootstrapper garantiza que todo plan sembrado lo traiga.
        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique('plans_code_unique');
            $table->unique(['business_type_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['business_type_id', 'code']);
            $table->unique('code');
            $table->dropConstrainedForeignId('business_type_id');
        });
    }
};
