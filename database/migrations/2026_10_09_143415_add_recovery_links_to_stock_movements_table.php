<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('demolition_project_id')->nullable()->after('inventory_id')->constrained()->restrictOnDelete();
            $table->foreignId('recovered_material_id')->nullable()->after('demolition_project_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recovered_material_id');
            $table->dropConstrainedForeignId('demolition_project_id');
        });
    }
};
