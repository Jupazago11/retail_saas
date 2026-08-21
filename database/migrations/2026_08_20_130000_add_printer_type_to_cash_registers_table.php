<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->string('printer_type')->nullable()->after('is_primary');
        });

        // Cajas nuevas nacen con printer_type = null a proposito (dispara el
        // modal obligatorio de configuracion inicial); las cajas que ya
        // existian antes de este cambio heredan en silencio el valor que la
        // empresa ya tenia en company_settings.printing.ticket_format, para
        // no interrumpir a nadie que ya estaba operando.
        $ticketFormatByCompany = DB::table('company_settings')
            ->where('group_key', 'printing')
            ->where('setting_key', 'ticket_format')
            ->pluck('value_string', 'company_id');

        DB::table('cash_registers')
            ->select('id', 'company_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($ticketFormatByCompany) {
                foreach ($rows as $row) {
                    DB::table('cash_registers')
                        ->where('id', $row->id)
                        ->update([
                            'printer_type' => $ticketFormatByCompany[$row->company_id] ?? 'thermal_80mm',
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn('printer_type');
        });
    }
};
