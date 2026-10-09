<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('vat_rate', 5, 4)->default(0.12);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('payment_method', 32);
            $table->decimal('amount_received', 12, 2);
            $table->decimal('change_due', 12, 2)->default(0);
            $table->string('payment_reference')->nullable();
            $table->string('status', 16)->default('completed');
            $table->timestamp('completed_at');
            $table->string('receipt_company_name')->nullable();
            $table->text('receipt_company_address')->nullable();
            $table->string('receipt_company_phone')->nullable();
            $table->timestamps();
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_transactions');
    }
};
