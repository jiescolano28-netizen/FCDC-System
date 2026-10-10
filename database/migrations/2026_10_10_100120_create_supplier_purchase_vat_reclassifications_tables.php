<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_purchase_vat_reclassifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_purchase_invoice_id')->nullable()->constrained(indexName: 'vatr_invoice_fk')->restrictOnDelete();
            $table->foreignId('cash_disbursement_id')->nullable()->constrained(indexName: 'vatr_cash_disbursement_fk')->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('journal_id')->unique()->constrained('accounting_journals')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->text('reason');
            $table->date('accounting_date');
            $table->foreignId('prepared_by')->constrained('employees');
            $table->foreignId('posted_by')->constrained('employees');
            $table->timestamps();
            $table->index(['supplier_purchase_invoice_id', 'accounting_date'], 'purchase_vat_reclass_invoice_date_idx');
            $table->index(['cash_disbursement_id', 'accounting_date'], 'purchase_vat_reclass_disbursement_date_idx');
        });

        Schema::create('supplier_purchase_vat_reclassification_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_purchase_vat_reclassification_id')->constrained('supplier_purchase_vat_reclassifications', indexName: 'vatrl_reclassification_fk')->restrictOnDelete();
            $table->foreignId('supplier_purchase_line_id')->nullable()->constrained('supplier_purchase_lines', indexName: 'vatrl_purchase_line_fk')->restrictOnDelete();
            $table->foreignId('cash_disbursement_line_id')->nullable()->constrained('cash_disbursement_lines', indexName: 'vatrl_cash_disbursement_line_fk')->restrictOnDelete();
            $table->foreignId('inventory_id')->nullable()->constrained(indexName: 'vatrl_inventory_fk')->restrictOnDelete();
            $table->foreignId('accounting_account_id')->constrained('accounting_accounts', indexName: 'vatrl_account_fk')->restrictOnDelete();
            $table->foreignId('consumed_accounting_account_id')->nullable()->constrained('accounting_accounts', indexName: 'vatrl_consumed_account_fk')->restrictOnDelete();
            $table->unsignedBigInteger('remaining_inventory_cents')->default(0);
            $table->unsignedBigInteger('consumed_cost_cents')->default(0);
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
            $table->unique(['supplier_purchase_vat_reclassification_id', 'supplier_purchase_line_id'], 'purchase_vat_reclass_purchase_line_unique');
            $table->unique(['supplier_purchase_vat_reclassification_id', 'cash_disbursement_line_id'], 'purchase_vat_reclass_disbursement_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_vat_reclassification_lines');
        Schema::dropIfExists('supplier_purchase_vat_reclassifications');
    }
};
