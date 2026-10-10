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
        Schema::create('opening_inventory_valuations', function (Blueprint $table) {
            $table->id();
            $table->string('book_key', 32)->unique();
            $table->date('cutover_date');
            $table->string('evidence_reference', 255);
            $table->string('status', 16)->default('draft');
            $table->foreignId('prepared_by')->constrained('employees')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('employees')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('opening_inventory_valuation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_inventory_valuation_id');
            $table->foreign('opening_inventory_valuation_id', 'opening_inv_val_line_fk')
                ->references('id')->on('opening_inventory_valuations')->restrictOnDelete();
            $table->foreignId('inventory_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->unsignedBigInteger('carrying_value_cents');
            $table->timestamps();
            $table->unique(['opening_inventory_valuation_id', 'inventory_id'], 'opening_inventory_valuation_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_inventory_valuation_lines');
        Schema::dropIfExists('opening_inventory_valuations');
    }
};
