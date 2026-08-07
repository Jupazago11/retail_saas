<?php

use App\Enums\CreditAccountStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default(CreditAccountStatus::Active->value);
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->decimal('available_credit', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'customer_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_accounts');
    }
};
