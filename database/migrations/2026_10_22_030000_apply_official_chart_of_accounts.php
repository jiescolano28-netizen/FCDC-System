<?php

use Database\Seeders\OfficialChartOfAccountsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_accounts')
            || DB::table('accounting_accounts')->count() === 0) {
            return;
        }

        app(OfficialChartOfAccountsSeeder::class)->seed();
    }

    public function down(): void
    {
        // The replacement is intentionally not reversed. Historical journal lines
        // and legacy account rows must remain available for reporting and audit.
    }
};
