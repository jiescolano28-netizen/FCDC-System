<?php

use App\Services\IncludeMissingPosVatRecords;
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
        Schema::create('pos_vat_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_transaction_id')->unique()->constrained('pos_transactions')->restrictOnDelete();
            $table->decimal('taxable_sales', 12, 2);
            $table->decimal('vat_rate', 5, 4);
            $table->decimal('output_vat', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamp('completed_at')->index();
            $table->timestamps();
        });

        app(IncludeMissingPosVatRecords::class)->handle();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_vat_records');
    }
};
