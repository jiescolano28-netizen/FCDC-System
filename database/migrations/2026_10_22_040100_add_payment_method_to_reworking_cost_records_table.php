<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reworking_cost_records', function (Blueprint $table) {
            // Required to identify the credit side of a reworking-cost journal
            // entry. Existing records remain eligible for review but cannot post
            // automatically until a settlement method is recorded.
            $table->string('payment_method', 100)->nullable()->after('input_vat');
        });
    }

    public function down(): void
    {
        Schema::table('reworking_cost_records', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
