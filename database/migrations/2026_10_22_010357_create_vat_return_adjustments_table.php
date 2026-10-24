<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_return_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter');
            $table->decimal('output_vat_uncollected_receivables', 15, 2)->default(0);
            $table->decimal('output_vat_recovered_receivables', 15, 2)->default(0);
            $table->decimal('input_tax_carried_over', 15, 2)->default(0);
            $table->decimal('deferred_capital_goods_input_tax_prior', 15, 2)->default(0);
            $table->decimal('transitional_input_tax', 15, 2)->default(0);
            $table->decimal('presumptive_input_tax', 15, 2)->default(0);
            $table->decimal('other_input_tax', 15, 2)->default(0);
            $table->string('other_input_tax_description', 255)->nullable();
            $table->decimal('deferred_capital_goods_input_tax_current', 15, 2)->default(0);
            $table->decimal('input_tax_vat_exempt_sales', 15, 2)->default(0);
            $table->decimal('vat_refund_tcc_claimed', 15, 2)->default(0);
            $table->decimal('input_vat_unpaid_payables', 15, 2)->default(0);
            $table->decimal('other_input_tax_deductions', 15, 2)->default(0);
            $table->string('other_input_tax_deductions_description', 255)->nullable();
            $table->decimal('input_vat_settled_unpaid_payables', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_return_adjustments');
    }
};
