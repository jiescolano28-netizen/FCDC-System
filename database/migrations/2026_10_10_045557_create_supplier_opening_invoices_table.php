<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_opening_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('supplier_code_snapshot', 30);
            $table->string('supplier_name_snapshot', 180);
            $table->string('supplier_legal_name_snapshot', 180)->nullable();
            $table->string('supplier_tax_identifier_snapshot', 80)->nullable();
            $table->text('supplier_address_snapshot')->nullable();
            $table->string('invoice_number', 100);
            $table->string('invoice_number_normalized', 100);
            $table->date('recognition_date');
            $table->date('due_date');
            $table->unsignedBigInteger('amount_cents');
            $table->text('description');
            $table->string('terms', 180)->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('opening_journal_id')->nullable()->constrained('accounting_journals')->restrictOnDelete();
            $table->foreignId('prepared_by')->constrained('employees');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('supplier_opening_invoices')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['supplier_id', 'invoice_number_normalized'], 'supplier_invoice_normalized_unique');
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_opening_invoices');
    }
};
