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
        Schema::table('accounting_journals', function (Blueprint $table) {
            $table->string('external_reference', 120)->nullable()->after('description');
            $table->foreignId('correction_of_id')->nullable()->after('external_reference')
                ->constrained('accounting_journals')->restrictOnDelete();
            $table->text('correction_reason')->nullable()->after('correction_of_id');
        });
        Schema::table('accounting_journal_lines', function (Blueprint $table) {
            $table->string('description', 255)->nullable()->after('accounting_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_journals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('correction_of_id');
            $table->dropColumn(['correction_reason', 'external_reference']);
        });
        Schema::table('accounting_journal_lines', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
