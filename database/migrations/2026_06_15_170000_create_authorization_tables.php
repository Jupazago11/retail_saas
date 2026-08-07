<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('module_code', 50);
            $table->string('status')->default(RecordStatus::Active->value);
            $table->timestamps();

            $table->index(['module_code', 'status']);
        });

        Schema::create('role_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('scope', 50)->default('company');
            $table->string('status')->default(RecordStatus::Active->value);
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

        Schema::create('company_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('display_name');
            $table->string('status')->default(RecordStatus::Active->value);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('company_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_role_permissions');
        Schema::dropIfExists('company_roles');
        Schema::dropIfExists('role_template_permissions');
        Schema::dropIfExists('role_templates');
        Schema::dropIfExists('permissions');
    }
};
