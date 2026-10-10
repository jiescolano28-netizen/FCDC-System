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
        Schema::create('accounting_ytd_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('book_key', 30)->default('FCDC');
            $table->unsignedSmallInteger('fiscal_year');
            $table->date('through_date');
            $table->string('evidence_reference', 255);
            $table->string('status', 20)->default('draft');
            $table->foreignId('prepared_by')->constrained('employees');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('employees');
            $table->timestamps();
            $table->unique(['book_key', 'fiscal_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_ytd_summaries');
    }
};
