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
        Schema::create('pos_transaction_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_transaction_id')->constrained('pos_transactions')->restrictOnDelete();
            $table->foreignId('inventory_id')->nullable()->constrained()->nullOnDelete();
            $table->string('inventory_code');
            $table->string('item_name');
            $table->string('category');
            $table->string('unit', 50);
            $table->decimal('quantity', 12, 2);
            $table->decimal('selling_price', 12, 2);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('line_subtotal', 12, 2);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_transaction_lines');
    }
};
