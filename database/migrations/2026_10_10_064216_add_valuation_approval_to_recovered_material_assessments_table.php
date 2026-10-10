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
                $table->foreignId('counterpart_accounting_account_id')->nullable();
            });
        }
        if (! Schema::hasColumn('recovered_material_assessments', 'valuation_approved_by')) {
            Schema::table('recovered_material_assessments', function (Blueprint $table) {
                $table->foreignId('valuation_approved_by')->nullable();
            });
        }
        foreach ([
            'counterpart_accounting_account_id' => ['accounting_accounts', 'rma_counterpart_account_fk'],
            'valuation_approved_by' => ['employees', 'rma_valuation_approved_by_fk'],
        ] as $column => [$referencedTable, $foreignKeyName]) {
            $hasForeignKey = collect(Schema::getForeignKeys('recovered_material_assessments'))
                ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === [$column]
                    && $foreignKey['foreign_table'] === $referencedTable);

            if (! $hasForeignKey) {
                Schema::table('recovered_material_assessments', function (Blueprint $table) use ($column, $referencedTable, $foreignKeyName) {
                    $table->foreign($column, $foreignKeyName)->references('id')->on($referencedTable)->nullOnDelete();
                });
            }
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
            $table->dropForeign('rma_valuation_approved_by_fk');
            $table->dropForeign('rma_counterpart_account_fk');
            $table->dropColumn([
                'valuation_approved_by',
                'counterpart_accounting_account_id',
                'assigned_unit_value_cents',
                'assigned_value_cents',
                'valuation_approved_at',
            ]);
        });
    }
};
