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
        Schema::create('supplier_purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('supplier_code_snapshot', 30);
            $table->string('supplier_name_snapshot', 180);
            $table->string('invoice_number', 100);
            $table->string('invoice_number_normalized', 100);
            $table->date('recognition_date');
            $table->date('due_date');
            $table->unsignedBigInteger('gross_amount_cents');
            $table->text('description');
            $table->string('terms', 180)->nullable();
            $table->boolean('receipt_confirmed')->default(false);
            $table->string('status', 20)->default('draft');
            $table->foreignId('prepared_by')->constrained('employees');
            $table->foreignId('posted_by')->nullable()->constrained('employees');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('accounting_journal_id')->nullable()->unique()->constrained('accounting_journals')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['supplier_id', 'invoice_number_normalized'], 'supplier_purchase_invoice_unique');
            $table->index(['recognition_date', 'due_date', 'status'], 'supplier_purchase_invoices_due_status_idx');
        });
        Schema::create('supplier_purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_purchase_invoice_id')->constrained('supplier_purchase_invoices')->cascadeOnDelete();
            $table->foreignId('inventory_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('accounting_account_id')->nullable()->constrained('accounting_accounts')->restrictOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 12, 2)->nullable();
            $table->unsignedBigInteger('line_amount_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_lines');
        Schema::dropIfExists('supplier_purchase_invoices');
    }
};
