<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printer_guides', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('instructions');
            $table->string('disk')->default('r2');
            $table->string('path')->nullable();
            $table->string('url')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_guides');
    }
};
