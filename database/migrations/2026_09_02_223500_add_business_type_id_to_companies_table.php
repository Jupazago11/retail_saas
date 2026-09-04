<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('business_type_id')->nullable()->after('owner_user_id')
                ->constrained('business_types')->nullOnDelete();
        });

        $now = now();

        DB::table('business_types')->insertOrIgnore([
            [
                'code' => 'general',
                'name' => 'Negocio general',
                'icon' => 'store',
                'status' => RecordStatus::Active->value,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'restaurant',
                'name' => 'Restaurante',
                'icon' => 'utensils',
                'status' => RecordStatus::Active->value,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // Toda empresa creada antes de que existiera este concepto es, por definicion,
        // un negocio general (unico vertical soportado hasta ahora).
        $generalId = DB::table('business_types')->where('code', 'general')->value('id');

        DB::table('companies')->whereNull('business_type_id')->update([
            'business_type_id' => $generalId,
        ]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_type_id');
        });
    }
};
