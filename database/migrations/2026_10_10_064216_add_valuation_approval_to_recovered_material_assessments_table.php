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
        if (! Schema::hasColumn('recovered_material_assessments', 'assigned_unit_value_cents')) {
            Schema::table('recovered_material_assessments', function (Blueprint $table) {
                $table->unsignedBigInteger('assigned_unit_value_cents')->nullable();
            });
        }
        if (! Schema::hasColumn('recovered_material_assessments', 'assigned_value_cents')) {
            Schema::table('recovered_material_assessments', function (Blueprint $table) {
                $table->unsignedBigInteger('assigned_value_cents')->nullable();
            });
        }
        if (! Schema::hasColumn('recovered_material_assessments', 'counterpart_accounting_account_id')) {
            Schema::table('recovered_material_assessments', function (Blueprint $table) {
                $table->foreignId('counterpart_accounting_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('recovered_material_assessments', 'valuation_approved_by')) {
            Schema::table('recovered_material_assessments', function (Blueprint $table) {
                $table->foreignId('valuation_approved_by')->nullable()->constrained('employees')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('recovered_material_assessments', 'valuation_approved_at')) {
            Schema::table('recovered_material_assessments', function (Blueprint $table) {
                $table->timestamp('valuation_approved_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('recovered_material_assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('valuation_approved_by');
            $table->dropConstrainedForeignId('counterpart_accounting_account_id');
            $table->dropColumn(['assigned_unit_value_cents', 'assigned_value_cents', 'valuation_approved_at']);
        });
    }
};
