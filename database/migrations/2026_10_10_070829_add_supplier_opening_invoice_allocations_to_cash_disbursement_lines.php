<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_disbursement_lines', function (Blueprint $table) {
            $table->foreignId('supplier_opening_invoice_id')->nullable()->after('supplier_purchase_invoice_id')
                ->constrained('supplier_opening_invoices')->restrictOnDelete();
            $table->index(['supplier_opening_invoice_id', 'cash_disbursement_id'], 'cash_disbursement_opening_invoice_allocations');
        });
    }

    public function down(): void
    {
        Schema::table('cash_disbursement_lines', function (Blueprint $table) {
            $table->dropIndex('cash_disbursement_opening_invoice_allocations');
            $table->dropConstrainedForeignId('supplier_opening_invoice_id');
        });
    }
};
