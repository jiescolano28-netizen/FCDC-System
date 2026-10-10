<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('valuation approval migration resumes when its first column already exists', function () {
    Schema::table('recovered_material_assessments', function (Blueprint $table): void {
        $table->dropConstrainedForeignId('valuation_approved_by');
        $table->dropForeign(['counterpart_accounting_account_id']);
        $table->dropColumn(['assigned_value_cents', 'valuation_approved_at']);
    });

    $migration = include database_path('migrations/2026_10_10_064216_add_valuation_approval_to_recovered_material_assessments_table.php');
    $migration->up();
    $foreignKeys = collect(Schema::getForeignKeys('recovered_material_assessments'));

    expect(Schema::hasColumn('recovered_material_assessments', 'assigned_unit_value_cents'))->toBeTrue()
        ->and(Schema::hasColumn('recovered_material_assessments', 'assigned_value_cents'))->toBeTrue()
        ->and(Schema::hasColumn('recovered_material_assessments', 'counterpart_accounting_account_id'))->toBeTrue()
        ->and(Schema::hasColumn('recovered_material_assessments', 'valuation_approved_by'))->toBeTrue()
        ->and(Schema::hasColumn('recovered_material_assessments', 'valuation_approved_at'))->toBeTrue()
        ->and($foreignKeys->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === ['counterpart_accounting_account_id'] && $foreignKey['foreign_table'] === 'accounting_accounts'))->toBeTrue()
        ->and($foreignKeys->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === ['valuation_approved_by'] && $foreignKey['foreign_table'] === 'employees'))->toBeTrue();
});
