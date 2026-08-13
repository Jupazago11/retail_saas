<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Simplifica el ciclo de vida de una compra a Pendiente (confirmed) /
 * Parcial / Pagada / Cancelada. "Borrador" desaparece (nunca se usaba en la
 * practica: editar solo funcionaba antes de que hubiera dinero o inventario
 * de por medio, asi que no aportaba nada frente a cancelar y crear de
 * nuevo). "Devuelta" se fusiona con "Cancelada": conceptualmente es la
 * misma reversa, con o sin inventario que revertir.
 *
 * Solo se migran los datos existentes: el default de la columna a nivel de
 * base de datos no se toca (requeriria SQL especifico por motor y aqui no
 * aporta nada, porque la aplicacion siempre manda 'status' explicito al
 * crear una compra, nunca depende del default).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchases')->where('status', 'draft')->update(['status' => 'confirmed']);
        DB::table('purchases')->where('status', 'returned')->update(['status' => 'cancelled']);
    }

    public function down(): void
    {
        // Los datos migrados no se pueden distinguir de vuelta con certeza
        // (ya no sabemos cuales 'confirmed' eran 'draft' antes), asi que no
        // hay reversa segura de datos aqui.
    }
};
