<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_purchase_invoices', function (Blueprint $table): void {
            $table->foreignId('correction_of_id')->nullable()->after('accounting_journal_id')
                ->constrained('supplier_purchase_invoices')->restrictOnDelete();
            $table->string('correction_reason', 4000)->nullable()->after('correction_of_id');
            $table->index('correction_of_id', 'supplier_purchase_invoice_corrections_idx');
        });

        Schema::create('supplier_purchase_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_purchase_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cash_disbursement_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('replacement_invoice_id')->nullable()->constrained('supplier_purchase_invoices')->restrictOnDelete();
            $table->foreignId('journal_id')->constrained('accounting_journals')->restrictOnDelete();
            $table->foreignId('corrected_journal_id')->nullable()->constrained('accounting_journals')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('original_amount_cents');
            $table->unsignedBigInteger('corrected_amount_cents');
            $table->bigInteger('refund_due_cents')->default(0);
            $table->text('reason');
            $table->date('accounting_date');
            $table->foreignId('prepared_by')->constrained('employees');
            $table->foreignId('posted_by')->constrained('employees');
            $table->timestamps();
            $table->index(['supplier_id', 'accounting_date'], 'supplier_purchase_corrections_supplier_date_idx');
        });
        Schema::table('supplier_purchase_invoices', function (Blueprint $table): void {
            $table->foreignId('supplier_purchase_correction_id')->nullable()->after('correction_reason')
                ->constrained('supplier_purchase_corrections')->restrictOnDelete();
        });
        Schema::create('supplier_purchase_correction_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_purchase_correction_id')->constrained('supplier_purchase_corrections')->restrictOnDelete();
            $table->foreignId('supplier_purchase_line_id')->nullable()->constrained('supplier_purchase_lines')->restrictOnDelete();
            $table->foreignId('inventory_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('accounting_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('consumed_accounting_account_id')->nullable()->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('cash_disbursement_line_id')->nullable()->constrained('cash_disbursement_lines')->restrictOnDelete();
            $table->string('direction', 10);
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('remaining_inventory_cents')->default(0);
            $table->unsignedBigInteger('consumed_cost_cents')->default(0);
            $table->timestamps();
            $table->index('supplier_purchase_correction_id', 'supplier_purchase_correction_lines_idx');
        });


        Schema::create('supplier_refund_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_purchase_correction_id')->constrained('supplier_purchase_corrections')->restrictOnDelete();
            $table->foreignId('money_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('journal_id')->unique()->constrained('accounting_journals')->restrictOnDelete();
            $table->string('reference', 100);
            $table->string('evidence_reference', 255);
            $table->unsignedBigInteger('amount_cents');
            $table->date('receipt_date');
            $table->foreignId('posted_by')->constrained('employees');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_refund_receipts');
        Schema::dropIfExists('supplier_purchase_correction_lines');
        Schema::table('supplier_purchase_invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_purchase_correction_id');
            $table->dropIndex('supplier_purchase_invoice_corrections_idx');
            $table->dropConstrainedForeignId('correction_of_id');
            $table->dropColumn('correction_reason');
        });
        Schema::dropIfExists('supplier_purchase_corrections');
    }
};
