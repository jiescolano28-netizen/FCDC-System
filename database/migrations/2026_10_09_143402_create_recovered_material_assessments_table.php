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
        $indexName = 'rma_material_created_at_idx';

        if (Schema::hasTable('recovered_material_assessments')) {
            Schema::whenTableDoesntHaveIndex(
                'recovered_material_assessments',
                $indexName,
                fn (Blueprint $table) => $table->index(['recovered_material_id', 'created_at'], $indexName),
            );

            return;
        }
        Schema::create('recovered_material_assessments', function (Blueprint $table) use ($indexName) {
            $table->id();
            $table->foreignId('recovered_material_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('accepted_quantity', 12, 2);
            $table->decimal('rejected_quantity', 12, 2);
            $table->string('rejection_reason', 255)->nullable();
            $table->foreignId('supersedes_assessment_id')->nullable()->constrained('recovered_material_assessments')->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->timestamps();
            $table->index(['recovered_material_id', 'created_at'], $indexName);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recovered_material_assessments');
    }
};
