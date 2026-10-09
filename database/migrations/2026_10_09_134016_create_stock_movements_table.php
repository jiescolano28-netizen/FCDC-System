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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('type', 32);
            $table->decimal('quantity', 12, 2);
            $table->string('reason_category', 64);
            $table->text('notes')->nullable();
            $table->string('reference', 255)->nullable();
            $table->date('effective_date');
            $table->timestamp('posted_at');
            $table->foreignId('reverses_movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->timestamps();
            $table->index(['inventory_id', 'effective_date', 'posted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
