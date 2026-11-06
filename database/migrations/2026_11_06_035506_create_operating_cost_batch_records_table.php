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
        Schema::create('operating_cost_batch_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedBigInteger('source_record_id');
            $table->foreignId('project_id')->nullable()->constrained('demolition_projects')->nullOnDelete();
            $table->string('project_name');
            $table->string('batch_number', 100);
            $table->date('record_date');
            $table->text('description');
            $table->decimal('total_operating_cost', 15, 2)->default(0);
            $table->decimal('input_vat', 15, 2)->default(0);
            $table->string('payment_method', 100)->nullable();
            $table->json('attachment')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'source_record_id']);
            $table->index(['record_date', 'batch_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operating_cost_batch_records');
    }
};
