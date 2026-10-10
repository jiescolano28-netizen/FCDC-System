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
        Schema::table('cash_disbursements', function (Blueprint $table) {
            $table->boolean('receipt_confirmed')->default(false)->after('payee');
        });
        Schema::table('cash_disbursement_lines', function (Blueprint $table) {
            $table->foreignId('inventory_id')->nullable()->after('description')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 2)->nullable()->after('inventory_id');
            $table->foreignId('stock_movement_id')->nullable()->unique()->after('quantity')->constrained()->restrictOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('cash_disbursement_line_id')->nullable()->unique()->constrained('cash_disbursement_lines')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropUnique(['cash_disbursement_line_id']);
            $table->dropConstrainedForeignId('cash_disbursement_line_id');
        });

        Schema::table('cash_disbursement_lines', function (Blueprint $table) {
            $table->dropUnique(['stock_movement_id']);
            $table->dropConstrainedForeignId('stock_movement_id');
            $table->dropColumn(['inventory_id', 'quantity']);
        });

        Schema::table('cash_disbursements', function (Blueprint $table) {
            $table->dropColumn('receipt_confirmed');
        });
    }
};
