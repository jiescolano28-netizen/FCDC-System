<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->bigInteger('value_cents')->nullable();
            $table->bigInteger('carrying_value_after_cents')->nullable();
            $table->foreignId('accounting_journal_id')->nullable()
                ->constrained('accounting_journals')->nullOnDelete();
            $table->string('source_reference', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounting_journal_id');
            $table->dropColumn(['value_cents', 'carrying_value_after_cents', 'source_reference']);
        });
    }
};
