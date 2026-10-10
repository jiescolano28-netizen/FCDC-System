<?php

use App\Livewire\Accounting\AccountsPayable;
use App\Livewire\Accounting\OpeningBooks;
use App\Models\AccountingAccount;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function supplierPayablesEmployee(array $permissions, string $name): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => $name,
        'email' => $name.'@example.com',
        'password' => Hash::make('supplier-password'),
    ]), ['accounting.view', ...$permissions]);
}

function supplierPayablesAccount(string $code, string $classification, string $type, string $balance): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => $classification,
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $balance,
        'is_active' => true,
        'approved_at' => now(),
    ]);
}

test('opening supplier invoices post only with an exactly matching AP opening and retain approval history', function () {
    $preparer = supplierPayablesEmployee(['accounting.maintain-suppliers', 'accounting.maintain-opening-books'], 'payable-preparer');
    $reviewer = supplierPayablesEmployee(['accounting.approve-opening-books'], 'payable-reviewer');
    $ap = supplierPayablesAccount('2100', 'accounts_payable', 'Liability', 'credit');
    $capital = supplierPayablesAccount('3000', 'capital', 'Equity', 'credit');

    $this->actingAs($preparer);
    Livewire::test(AccountsPayable::class)
        ->set('supplierCode', 'SUP-001')
        ->set('supplierName', 'Northwind Materials')
        ->set('supplierAddress', '1 Market Road')
        ->call('saveSupplier')
        ->assertHasNoErrors()
        ->assertSee('Northwind Materials');

    Livewire::test(AccountsPayable::class)
        ->set('supplierId', '1')
        ->set('invoiceNumber', '  inv-100  ')
        ->set('recognitionDate', '2026-07-01')
        ->set('dueDate', '2026-07-31')
        ->set('amount', '125.50')
        ->set('description', 'Opening building supplies')
        ->set('terms', 'Net 30')
        ->call('saveOpeningInvoice')
        ->assertHasNoErrors()
        ->assertSee('Draft');

    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-07-01')
        ->set('lines', [
            ['accountId' => (string) $ap->id, 'debit' => '', 'credit' => '125.50'],
            ['accountId' => (string) $capital->id, 'debit' => '125.50', 'credit' => ''],
        ])
        ->call('saveOpening')
        ->assertHasNoErrors();

    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)->call('approveOpening')->assertHasNoErrors()->assertSee('Opening supplier schedule reconciled');

    $this->actingAs($preparer);
    Livewire::test(AccountsPayable::class)
        ->call('editSupplier', 1)
        ->set('supplierName', 'Northwind Updated')
        ->set('supplierAddress', '2 Changed Road')
        ->call('saveSupplier')
        ->assertHasNoErrors();
    Livewire::test(AccountsPayable::class)
        ->assertSee('Northwind Updated')
        ->call('showInvoice', 1)
        ->assertSee('Northwind Materials')
        ->assertSee('1 Market Road')
        ->assertDontSee('2 Changed Road')
        ->assertSee('Opening building supplies')
        ->assertSee('Net 30')
        ->assertSee('Approved by')
        ->assertSee('payable-reviewer')
        ->assertSee('125.50');

    expect(DB::table('accounting_journals')->where('status', 'posted')->count())->toBe(1)
        ->and(DB::table('accounting_journal_lines')->where('accounting_account_id', $ap->id)->sum('credit_cents'))->toBe(12550)
        ->and(DB::table('accounting_journal_lines')->count())->toBe(2)
        ->and(DB::table('supplier_opening_invoices')->where('status', 'posted')->value('approved_by'))->toBe($reviewer->id)
        ->and(DB::table('supplier_opening_invoices')->where('status', 'posted')->value('prepared_by'))->toBe($preparer->id)
        ->and(DB::table('accounting_journals')->where('source_type', 'opening')->count())->toBe(1);
});

test('opening AP approval rejects a mismatched schedule and normalized duplicate invoice but allows another supplier', function () {
    $preparer = supplierPayablesEmployee(['accounting.maintain-suppliers', 'accounting.maintain-opening-books'], 'duplicate-preparer');
    $reviewer = supplierPayablesEmployee(['accounting.approve-opening-books'], 'duplicate-reviewer');
    $ap = supplierPayablesAccount('2100', 'accounts_payable', 'Liability', 'credit');
    $capital = supplierPayablesAccount('3000', 'capital', 'Equity', 'credit');

    $this->actingAs($preparer);
    $component = Livewire::test(AccountsPayable::class)
        ->set('supplierCode', 'SUP-001')->set('supplierName', 'Northwind Materials')->call('saveSupplier')
        ->set('supplierCode', 'SUP-002')->set('supplierName', 'Southwind Materials')->call('saveSupplier');
    expect(DB::table('suppliers')->count())->toBe(2);

    Livewire::test(AccountsPayable::class)
        ->set('supplierId', '1')->set('invoiceNumber', ' Inv-9 ')->set('recognitionDate', '2026-07-01')
        ->set('dueDate', '2026-07-31')->set('amount', '100.00')->set('description', 'Opening payable')
        ->call('saveOpeningInvoice')->assertHasNoErrors();
    Livewire::test(AccountsPayable::class)
        ->set('supplierId', '1')->set('invoiceNumber', 'inv-9')->set('recognitionDate', '2026-07-01')
        ->set('dueDate', '2026-07-31')->set('amount', '10.00')->set('description', 'Duplicate')
        ->call('saveOpeningInvoice')->assertHasErrors('invoiceNumber');
    Livewire::test(AccountsPayable::class)
        ->set('supplierId', '2')->set('invoiceNumber', ' inv-9 ')->set('recognitionDate', '2026-07-01')
        ->set('dueDate', '2026-07-31')->set('amount', '25.00')->set('description', 'Other supplier')
        ->call('saveOpeningInvoice')->assertHasNoErrors();

    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-07-01')
        ->set('lines', [
            ['accountId' => (string) $ap->id, 'debit' => '', 'credit' => '124.99'],
            ['accountId' => (string) $capital->id, 'debit' => '124.99', 'credit' => ''],
        ])
        ->call('saveOpening')->assertHasNoErrors();

    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)->call('approveOpening')->assertHasErrors('opening');

    expect(DB::table('accounting_journals')->where('status', 'posted')->count())->toBe(0)
        ->and(DB::table('supplier_opening_invoices')->where('status', 'posted')->count())->toBe(0)
        ->and(DB::table('supplier_opening_invoices')->count())->toBe(2);
});

test('supplier maintenance and opening invoice preparation require their own capabilities', function () {
    $viewer = supplierPayablesEmployee([], 'payable-viewer');
    $this->actingAs($viewer);

    Livewire::test(AccountsPayable::class)
        ->set('supplierCode', 'SUP-001')
        ->set('supplierName', 'Restricted Supplier')
        ->call('saveSupplier')->assertForbidden();
    Livewire::test(AccountsPayable::class)->call('saveOpeningInvoice')->assertForbidden();

    expect(DB::table('suppliers')->count())->toBe(0)
        ->and(DB::table('supplier_opening_invoices')->count())->toBe(0);
});
