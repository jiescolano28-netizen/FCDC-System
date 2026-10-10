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
        Schema::create('accounting_ytd_summary_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_ytd_summary_id')->constrained('accounting_ytd_summaries');
            $table->foreignId('accounting_account_id')->constrained('accounting_accounts');
            $table->bigInteger('amount_cents');
            $table->timestamps();
            $table->unique(['accounting_ytd_summary_id', 'accounting_account_id'], 'acct_ytd_lines_summary_account_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_ytd_summary_lines');
    }
};
