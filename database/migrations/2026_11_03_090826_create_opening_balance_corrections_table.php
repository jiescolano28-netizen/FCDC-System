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
        Schema::create('opening_balance_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opening_journal_id')->constrained('accounting_journals')->restrictOnDelete();
            $table->string('correction_type', 32);
            $table->string('status', 24)->default('pending_review');
            $table->date('posting_date');
            $table->text('reason');
            $table->json('details');
            $table->foreignId('prepared_by')->constrained('employees')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('employees')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('accounting_journals')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('employees')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'posting_date'], 'opening_correction_status_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_balance_corrections');
    }
};
