<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 180)->index();
            $table->string('legal_name', 180)->nullable();
            $table->string('tax_identifier', 80)->nullable();
            $table->text('address')->nullable();
            $table->string('contact_name', 150)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('phone', 50)->nullable();
            $table->foreignId('created_by')->constrained('employees');
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
