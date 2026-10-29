<?php

use App\Livewire\Pos\PointOfSale;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\PosTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function posWithoutOpeningEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'pos.no.opening.'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => Hash::make('secret-password'),
    ]), ['pos.checkout']);
}

function posWithoutOpeningAccount(string $code, string $type, string $classification, string $normalBalance): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => ucfirst(str_replace('_', ' ', $classification)),
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $normalBalance,
        'is_active' => true,
        'approved_at' => now(),
    ]);
}

test('completed POS sales post balanced journals without an approved opening cutover', function () {
    $employee = posWithoutOpeningEmployee();
    $this->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Unopened board', 'category' => 'Lumber', 'qty' => '10.00', 'unit' => 'piece',
        'unit_cost' => '50.00', 'selling_price' => '180.00', 'reorder_level' => '0',
    ]);
    $accounts = [
        'inventory' => posWithoutOpeningAccount('1200', 'Asset', 'inventory', 'debit'),
        'cash' => posWithoutOpeningAccount('1210', 'Asset', 'cash', 'debit'),
        'sales' => posWithoutOpeningAccount('1220', 'Revenue', 'sales', 'credit'),
        'output_vat' => posWithoutOpeningAccount('1230', 'Liability', 'output_vat', 'credit'),
        'cogs' => posWithoutOpeningAccount('1240', 'Expense', 'cost_of_goods_sold', 'debit'),
    ];
    foreach (['inventory', 'cash', 'sales', 'output_vat', 'cogs'] as $source) {
        AccountingPostingMapping::create([
            'source' => $source,
            'accounting_account_id' => $accounts[$source]->id,
            'approved_at' => now(),
            'approved_by' => $employee->id,
        ]);
    }

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $item->id)
        ->set('amountReceived', '201.60')
        ->call('checkout')
        ->assertHasNoErrors();

    $sale = PosTransaction::query()->sole();
    $journal = AccountingJournal::query()
        ->where('source_type', 'pos_sale')
        ->where('source_id', (string) $sale->id)
        ->with('lines')
        ->sole();

    expect($journal->status)->toBe('posted')
        ->and($journal->approved_by)->not->toBeNull()
        ->and($journal->lines->sum('debit_cents'))->toBe(25160)
        ->and($journal->lines->sum('debit_cents'))->toBe($journal->lines->sum('credit_cents'))
        ->and(AccountingJournal::query()->where('source_type', 'opening')->count())->toBe(0);
});
