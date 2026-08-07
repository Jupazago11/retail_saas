<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('status')->default(RecordStatus::Active->value);
            $table->string('billing_period', 30)->default('monthly');
            $table->decimal('base_price', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['status', 'billing_period']);
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('status')->default(RecordStatus::Active->value);
            $table->timestamps();

            $table->index(['status']);
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('code', 120)->unique();
            $table->string('name');
            $table->string('status')->default(RecordStatus::Active->value);
            $table->timestamps();

            $table->index(['module_id', 'status']);
        });

        Schema::create('plan_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['plan_id', 'module_id']);
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['plan_id', 'feature_id']);
        });

        Schema::create('plan_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('limit_key', 80);
            $table->integer('limit_value');
            $table->timestamps();

            $table->unique(['plan_id', 'limit_key']);
        });

        Schema::create('subscription_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users');
            $table->string('name');
            $table->string('status')->default(RecordStatus::Active->value);
            $table->unsignedInteger('max_companies')->nullable();
            $table->string('discount_type', 30)->nullable();
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['owner_user_id', 'status']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bundle_id')->nullable()->constrained('subscription_bundles')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('trialing');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('billing_snapshot')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['bundle_id', 'status']);
            $table->index(['plan_id', 'status']);
        });

        Schema::create('subscription_bundle_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('subscription_bundles')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['bundle_id', 'company_id']);
            $table->unique(['company_id']);
        });

        Schema::create('company_module_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->boolean('enabled');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'module_id']);
        });

        Schema::create('company_feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'feature_id']);
        });

        Schema::create('company_limit_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('limit_key', 80);
            $table->integer('limit_value');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'limit_key']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('discount_type', 30);
            $table->decimal('discount_value', 12, 2);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('total_uses_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->unsignedInteger('per_company_limit')->nullable();
            $table->string('status')->default(RecordStatus::Active->value);
            $table->timestamps();

            $table->index(['status', 'starts_at', 'expires_at']);
        });

        Schema::create('coupon_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['coupon_id', 'plan_id']);
        });

        Schema::create('coupon_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bundle_id')->constrained('subscription_bundles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['coupon_id', 'bundle_id']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('applied_amount', 12, 2);
            $table->json('applied_snapshot')->nullable();
            $table->timestamps();

            $table->index(['coupon_id', 'company_id']);
            $table->index(['subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupon_bundles');
        Schema::dropIfExists('coupon_plans');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('company_limit_overrides');
        Schema::dropIfExists('company_feature_overrides');
        Schema::dropIfExists('company_module_overrides');
        Schema::dropIfExists('subscription_bundle_companies');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_bundles');
        Schema::dropIfExists('plan_limits');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plan_modules');
        Schema::dropIfExists('features');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('plans');
    }
};
