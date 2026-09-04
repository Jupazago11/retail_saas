<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rectangulo oscuro en el plano para marcar espacio no utilizable
        // (columna, escalera, pared) y diferenciarlo a simple vista de las
        // mesas y las cajas. Puramente visual/de layout: sin nombre, sin
        // estado operativo, sin ninguna relacion con ventas u ordenes — por
        // eso, a diferencia de dining_tables, se borra fisico (sin
        // softDeletes ni status) cuando el dueño lo quita del plano.
        Schema::create('dining_obstacles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->decimal('pos_x', 5, 2);
            $table->decimal('pos_y', 5, 2);
            $table->decimal('width', 5, 2)->default(10);
            $table->decimal('height', 5, 2)->default(10);
            $table->timestamps();

            $table->index(['company_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_obstacles');
    }
};
