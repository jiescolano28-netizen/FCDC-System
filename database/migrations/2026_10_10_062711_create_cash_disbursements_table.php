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
        Schema::create('cash_disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 100)->unique();
            $table->string('payee', 180);
            $table->date('payment_date');
            $table->string('method', 40);
            $table->string('check_number', 80)->nullable();
            $table->foreignId('money_account_id')->constrained('accounting_accounts');
            $table->text('description');
            $table->string('evidence_reference', 255);
            $table->unsignedBigInteger('amount_cents');
            $table->string('status', 20)->default('draft');
            $table->foreignId('posting_period_id')->constrained('accounting_posting_periods');
            $table->foreignId('journal_id')->nullable()->constrained('accounting_journals');
            $table->foreignId('reversal_of_id')->nullable()->constrained('cash_disbursements');
            $table->text('correction_reason')->nullable();
            $table->foreignId('prepared_by')->constrained('employees');
            $table->foreignId('posted_by')->nullable()->constrained('employees');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('cash_disbursement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_disbursement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_account_id')->constrained('accounting_accounts');
            $table->string('description', 255);
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_disbursement_lines');
        Schema::dropIfExists('cash_disbursements');
    }
};
