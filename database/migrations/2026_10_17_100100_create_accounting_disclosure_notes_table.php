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
        Schema::create('accounting_disclosure_notes', function (Blueprint $table) {
            $table->id();
            $table->string('book_key', 30)->default('FCDC');
            $table->date('reporting_period_start');
            $table->date('reporting_period_end');
            $table->string('note_reference', 40);
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('disclosure_type', 20)->default('manual');
            $table->string('source_metric', 60)->nullable();
            $table->bigInteger('generated_amount_cents')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('prepared_by')->constrained('employees');
            $table->foreignId('approved_by')->nullable()->constrained('employees');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('employees');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['book_key', 'reporting_period_start', 'reporting_period_end', 'note_reference'],
                'acct_disclosure_period_reference_unique',
            );
            $table->index(['book_key', 'reporting_period_end'], 'acct_disclosure_period_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_disclosure_notes');
    }
};
