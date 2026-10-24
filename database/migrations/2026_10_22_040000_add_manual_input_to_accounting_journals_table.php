<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_journals', function (Blueprint $table) {
            $table->boolean('manual_input')->default(false)->after('entry_type');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_journals', function (Blueprint $table) {
            $table->dropColumn('manual_input');
        });
    }
};
