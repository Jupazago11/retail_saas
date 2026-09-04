<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status')->default(RecordStatus::Active->value);
            // Estado operativo en vivo (libre/ocupada/reservada), separado del
            // archivado logico (status). Nunca se serializa en payload_snapshot.
            $table->string('occupancy_status')->default('free');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'branch_id', 'name']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
    }
};
