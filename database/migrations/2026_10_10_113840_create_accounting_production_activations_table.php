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
        Schema::create('accounting_production_activations', function (Blueprint $table) {
            $table->id();
            $table->string('book_key', 32)->unique();
            $table->foreignId('activated_by')->constrained('employees')->restrictOnDelete();
            $table->timestamp('activated_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_production_activations');
    }
};
