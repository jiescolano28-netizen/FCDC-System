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
        Schema::create('recovered_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demolition_project_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('material');
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 50);
            $table->string('condition', 10);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['demolition_project_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recovered_materials');
    }
};
