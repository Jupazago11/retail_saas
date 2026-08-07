<?php

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('credit_account_id')->nullable();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method_code', 50);
            $table->string('status')->default(PaymentStatus::Confirmed->value);
            $table->decimal('amount', 14, 2);
            $table->string('reference', 120)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'sale_id', 'status']);
            $table->index(['company_id', 'cash_session_id', 'payment_method_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
