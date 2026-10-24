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
        Schema::create('operating_cost_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('expense_date');
            $table->string('reference_number', 100);
            $table->string('expense_category', 100);
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 100);
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 120)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->string('attachment_disk', 30)->nullable();
            $table->timestamps();

            $table->index('reference_number');
            $table->index(['expense_date', 'expense_category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operating_cost_records');
    }
};
