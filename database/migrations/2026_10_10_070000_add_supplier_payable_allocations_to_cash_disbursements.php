<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_disbursements', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('payee')->constrained()->restrictOnDelete();
        });

        Schema::table('cash_disbursement_lines', function (Blueprint $table) {
            $table->foreignId('supplier_purchase_invoice_id')->nullable()->after('accounting_account_id')->constrained()->restrictOnDelete();
            $table->index(['supplier_purchase_invoice_id', 'cash_disbursement_id'], 'cash_disbursement_invoice_allocations');
        });
    }

    public function down(): void
    {
        Schema::table('cash_disbursement_lines', function (Blueprint $table) {
            $table->dropIndex('cash_disbursement_invoice_allocations');
            $table->dropConstrainedForeignId('supplier_purchase_invoice_id');
        });
        Schema::table('cash_disbursements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
