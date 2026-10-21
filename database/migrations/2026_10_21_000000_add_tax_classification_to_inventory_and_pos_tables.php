<?php

use App\Support\TaxClassification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->string('tax_classification', 32)->default(TaxClassification::VATABLE)->after('selling_price');
        });

        Schema::table('pos_transactions', function (Blueprint $table) {
            $table->string('tax_classification', 32)->default(TaxClassification::VATABLE)->after('subtotal');
        });

        Schema::table('pos_transaction_lines', function (Blueprint $table) {
            $table->string('tax_classification', 32)->default(TaxClassification::VATABLE)->after('category');
        });

        Schema::table('pos_vat_records', function (Blueprint $table) {
            $table->string('tax_classification', 32)->default(TaxClassification::VATABLE)->after('pos_transaction_id');
            $table->decimal('zero_rated_sales', 12, 2)->default(0)->after('taxable_sales');
            $table->decimal('exempt_sales', 12, 2)->default(0)->after('zero_rated_sales');
            $table->decimal('non_vat_sales', 12, 2)->default(0)->after('exempt_sales');
        });
    }

    public function down(): void
    {
        Schema::table('pos_vat_records', function (Blueprint $table) {
            $table->dropColumn(['tax_classification', 'zero_rated_sales', 'exempt_sales', 'non_vat_sales']);
        });

        Schema::table('pos_transaction_lines', function (Blueprint $table) {
            $table->dropColumn('tax_classification');
        });

        Schema::table('pos_transactions', function (Blueprint $table) {
            $table->dropColumn('tax_classification');
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('tax_classification');
        });
    }
};
