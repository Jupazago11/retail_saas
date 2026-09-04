<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_order_items', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('quantity')->constrained('users')->nullOnDelete();
            $table->foreignId('modified_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            // Se prende cuando el mesero/cajero edita cantidad o quita un
            // plato despues de creado — cocina lo muestra como "novedad".
            $table->boolean('is_modified')->default(false)->after('kitchen_status');
        });
    }

    public function down(): void
    {
        Schema::table('dining_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('modified_by');
            $table->dropColumn('is_modified');
        });
    }
};
