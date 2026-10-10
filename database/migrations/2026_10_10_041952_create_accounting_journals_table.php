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
        Schema::create('accounting_journals', function (Blueprint $table) {
            $table->id();
            $table->string('book_key', 30)->default('FCDC');
            $table->string('reference', 80)->unique();
            $table->string('source_type', 40);
            $table->string('source_id', 100);
            $table->date('accounting_date');
            $table->foreignId('posting_period_id')->constrained('accounting_posting_periods');
            $table->text('description');
            $table->string('status', 20)->default('draft');
            $table->foreignId('prepared_by')->constrained('employees');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('employees');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('employees');
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
            $table->index(['posting_period_id', 'accounting_date', 'status'], 'acct_journal_period_date_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_journals');
    }
};
