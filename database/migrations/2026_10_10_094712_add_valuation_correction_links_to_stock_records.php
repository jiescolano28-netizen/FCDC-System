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
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('correction_of_movement_id')->nullable()
                ->constrained('stock_movements')->restrictOnDelete();
            $table->text('correction_reason')->nullable();
            $table->bigInteger('correction_total_delta_cents')->nullable();
        });

    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('correction_of_movement_id');
            $table->dropColumn(['correction_reason', 'correction_total_delta_cents']);
        });
    }
};
