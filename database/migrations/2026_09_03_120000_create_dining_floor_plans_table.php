<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_floor_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            // Puntos del contorno del salon, en porcentaje (0-100) de un
            // viewBox cuadrado fijo — asi el plano escala solo sin depender
            // de la resolucion de pantalla de quien lo dibujo o lo mira.
            $table->json('outline_points');
            $table->timestamps();

            $table->unique(['company_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_floor_plans');
    }
};
