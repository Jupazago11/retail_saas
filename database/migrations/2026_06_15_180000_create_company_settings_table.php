<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('group_key', 80);
            $table->string('setting_key', 120);
            $table->string('value_type', 20);
            $table->string('value_string')->nullable();
            $table->integer('value_integer')->nullable();
            $table->decimal('value_decimal', 14, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'group_key', 'setting_key']);
            $table->index(['company_id', 'group_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
