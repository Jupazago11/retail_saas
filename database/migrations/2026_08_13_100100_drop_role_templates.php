<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'role_template_id']);
            $table->dropConstrainedForeignId('role_template_id');
        });

        Schema::dropIfExists('role_template_permissions');
        Schema::dropIfExists('role_templates');
    }

    public function down(): void
    {
        Schema::create('role_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('scope', 50)->default('company');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['scope', 'status']);
        });

        Schema::create('role_template_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_template_id', 'permission_id']);
        });

        Schema::table('company_user', function (Blueprint $table) {
            $table->foreignId('role_template_id')
                ->nullable()
                ->after('company_role')
                ->constrained('role_templates')
                ->nullOnDelete();

            $table->index(['company_id', 'role_template_id']);
        });
    }
};
