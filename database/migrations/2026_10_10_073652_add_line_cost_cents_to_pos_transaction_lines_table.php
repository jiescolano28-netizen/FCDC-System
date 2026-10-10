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
        Schema::table('pos_transaction_lines', function (Blueprint $table) {
            $table->bigInteger('line_cost_cents')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_transaction_lines', function (Blueprint $table) {
            $table->dropColumn('line_cost_cents');
        });
    }
};
