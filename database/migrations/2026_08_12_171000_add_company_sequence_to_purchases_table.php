<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->unsignedInteger('company_sequence')->nullable()->after('company_id');
        });

        $sequencesByCompany = [];

        DB::table('purchases')
            ->select(['id', 'company_id'])
            ->orderBy('company_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $purchase) use (&$sequencesByCompany) {
                $companyId = (int) $purchase->company_id;
                $sequence = ($sequencesByCompany[$companyId] ?? 0) + 1;
                $sequencesByCompany[$companyId] = $sequence;

                DB::table('purchases')
                    ->where('id', $purchase->id)
                    ->update(['company_sequence' => $sequence]);
            });

        Schema::table('purchases', function (Blueprint $table) {
            $table->unique(['company_id', 'company_sequence']);
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'company_sequence']);
            $table->dropColumn('company_sequence');
        });
    }
};
