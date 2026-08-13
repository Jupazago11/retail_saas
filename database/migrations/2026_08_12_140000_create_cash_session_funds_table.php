<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_session_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'cash_session_id']);
        });

        // Migra las bases guardadas como json (sesiones abiertas con la
        // version anterior) a filas reales, para que tambien se puedan
        // editar/agregar sin perder lo que ya existia.
        DB::table('cash_sessions')
            ->whereNotNull('opening_funds')
            ->orderBy('id')
            ->each(function ($session) {
                $funds = json_decode($session->opening_funds ?? '[]', true) ?: [];

                foreach ($funds as $fund) {
                    DB::table('cash_session_funds')->insert([
                        'company_id' => $session->company_id,
                        'cash_session_id' => $session->id,
                        'label' => $fund['label'] ?? 'Base',
                        'amount' => $fund['amount'] ?? 0,
                        'created_by' => $session->opened_by,
                        'created_at' => $session->opened_at ?? now(),
                        'updated_at' => $session->opened_at ?? now(),
                    ]);
                }
            });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropColumn('opening_funds');
        });
    }

    public function down(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->json('opening_funds')->nullable()->after('opening_amount');
        });

        Schema::dropIfExists('cash_session_funds');
    }
};
