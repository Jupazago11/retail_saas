<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->foreignId('role_template_id')
                ->nullable()
                ->after('company_role')
                ->constrained('role_templates')
                ->nullOnDelete();

            $table->foreignId('company_role_id')
                ->nullable()
                ->after('role_template_id')
                ->constrained('company_roles')
                ->nullOnDelete();

            $table->index(['company_id', 'role_template_id']);
            $table->index(['company_id', 'company_role_id']);
        });
    }

    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_role_id');
            $table->dropConstrainedForeignId('role_template_id');
        });
    }
};
