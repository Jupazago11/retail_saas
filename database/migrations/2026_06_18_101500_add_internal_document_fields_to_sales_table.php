<?php

use App\Services\Sales\SaleDocumentNumberGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedInteger('document_sequence')->nullable()->after('warehouse_id');
            $table->string('document_number', 30)->nullable()->after('document_sequence');
        });

        $sequencesByCompany = [];

        DB::table('sales')
            ->select(['id', 'company_id'])
            ->orderBy('company_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $sale) use (&$sequencesByCompany) {
                $companyId = (int) $sale->company_id;
                $sequence = ($sequencesByCompany[$companyId] ?? 0) + 1;
                $sequencesByCompany[$companyId] = $sequence;

                DB::table('sales')
                    ->where('id', $sale->id)
                    ->update([
                        'document_sequence' => $sequence,
                        'document_number' => app(SaleDocumentNumberGenerator::class)->format('VTA-', $sequence),
                    ]);
            });

        Schema::table('sales', function (Blueprint $table) {
            $table->unique(['company_id', 'document_sequence']);
            $table->unique(['company_id', 'document_number']);
            $table->index(['company_id', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'document_sequence']);
            $table->dropUnique(['company_id', 'document_number']);
            $table->dropIndex(['company_id', 'document_number']);
            $table->dropColumn(['document_sequence', 'document_number']);
        });
    }
};
