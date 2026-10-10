<?php

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\OpeningInventoryValuation;
use App\Models\OpeningInventoryValuationLine;
use App\Models\Supplier;
use App\Services\Accounting\SupplierPurchaseService;
use App\Services\Inventory\RecordStockIn;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('supplier purchase drafts persist line allocations without payable stock or journal effects', function () {
    $actor = grantEmployeeTestPermissions(Employee::create([
        'username' => 'purchase.'.uniqid(), 'password' => Hash::make('password'), 'first_name' => 'Purchase',
        'last_name' => 'Accountant', 'email' => uniqid().'@example.test',
    ]), ['accounting.prepare-supplier-purchases']);
    $supplier = Supplier::create(['code' => 'SUP-1', 'name' => 'Building Materials', 'created_by' => $actor->id]);
    $equipment = AccountingAccount::create([
        'code' => '1600', 'name' => 'Equipment', 'type' => 'Asset', 'classification' => 'equipment',
        'normal_balance' => 'debit', 'is_active' => true, 'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    $service = app(SupplierPurchaseService::class);
    $attributes = [
        'supplier_id' => $supplier->id, 'invoice_number' => 'EQ-001', 'recognition_date' => '2026-10-01',
        'due_date' => '2026-11-01', 'description' => 'Equipment and supplies', 'terms' => 'Net 30',
        'receipt_confirmed' => true,
        'lines' => [
            ['inventory_id' => '', 'accounting_account_id' => $equipment->id, 'description' => 'Excavator equipment', 'quantity' => '', 'amount' => '50000.00'],
            ['inventory_id' => '', 'accounting_account_id' => $equipment->id, 'description' => 'Replacement part', 'quantity' => '', 'amount' => '200.00'],
        ],
    ];

    $draft = $service->saveDraft($attributes, $actor->id);

    expect($draft->status)->toBe('draft')
        ->and($draft->gross_amount_cents)->toBe(5020000)
        ->and($draft->lines)->toHaveCount(2)
        ->and(DB::table('accounting_journals')->count())->toBe(0)
        ->and(DB::table('stock_movements')->whereNotNull('accounting_journal_id')->count())->toBe(0);

    expect(fn () => $service->saveDraft([...$attributes, 'invoice_number' => ' eq-001 '], $actor->id))
        ->toThrow(ValidationException::class);
    $differentSupplier = Supplier::create(['code' => 'SUP-OTHER', 'name' => 'Other Supplier', 'created_by' => $actor->id]);
    $otherInvoice = $service->saveDraft([...$attributes, 'supplier_id' => $differentSupplier->id], $actor->id);
    expect($otherInvoice->invoice_number_normalized)->toBe('EQ-001');
    $service->deleteDraft($otherInvoice->id);
    $invalidAmount = $attributes;
    $invalidAmount['lines'][0]['amount'] = '0.00';
    expect(fn () => $service->saveDraft($invalidAmount, $actor->id))->toThrow(ValidationException::class);
    $service->deleteDraft($draft->id);
    expect(DB::table('supplier_purchase_invoices')->count())->toBe(0)
        ->and(DB::table('accounting_journals')->count())->toBe(0);
});
test('standalone stock-in cannot reuse a supplier invoice reference', function () {
    $actor = grantEmployeeTestPermissions(Employee::create([
        'username' => 'purchase.'.uniqid(), 'password' => Hash::make('password'), 'first_name' => 'Purchase',
        'last_name' => 'Accountant', 'email' => uniqid().'@example.test',
    ]), ['accounting.prepare-supplier-purchases', 'inventory.movements.record']);
    $supplier = Supplier::create(['code' => 'SUP-REF', 'name' => 'Supplier', 'created_by' => $actor->id]);
    $item = Inventory::create(['name' => 'Receipt item', 'category' => 'Materials', 'qty' => '0.00', 'unit' => 'piece', 'unit_cost' => '1.00', 'reorder_level' => '0']);
    app(SupplierPurchaseService::class)->saveDraft([
        'supplier_id' => $supplier->id, 'invoice_number' => 'INV-100', 'recognition_date' => '2026-10-01',
        'due_date' => '2026-10-31', 'description' => 'Supplier receipt', 'receipt_confirmed' => false,
        'lines' => [['inventory_id' => $item->id, 'accounting_account_id' => '', 'description' => 'Material', 'quantity' => '2.00', 'amount' => '10.00']],
    ], $actor->id);

    expect(fn () => app(RecordStockIn::class)->handle($item->id, 2.0, 'purchase', null, 'inv-100', '2026-01-01', $actor->id))
        ->toThrow(ValidationException::class);
    expect($item->fresh()->qty)->toBe('0.00')
        ->and(DB::table('stock_movements')->where('reference', 'inv-100')->count())->toBe(0);
});

test('posting an unconfirmed supplier invoice leaves AP stock and journals untouched', function () {
    $actor = grantEmployeeTestPermissions(Employee::create([
        'username' => 'purchase.'.uniqid(), 'password' => Hash::make('password'), 'first_name' => 'Purchase',
        'last_name' => 'Accountant', 'email' => uniqid().'@example.test',
    ]), ['accounting.post-supplier-purchases']);
    $supplier = Supplier::create(['code' => 'SUP-2', 'name' => 'Cement Supplier', 'created_by' => $actor->id]);
    $account = AccountingAccount::create([
        'code' => '6100', 'name' => 'Repairs Expense', 'type' => 'Expense', 'classification' => 'operating_expense',
        'normal_balance' => 'debit', 'is_active' => true, 'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    $invoice = app(SupplierPurchaseService::class)->saveDraft([
        'supplier_id' => $supplier->id, 'invoice_number' => 'C-100', 'recognition_date' => '2026-10-01',
        'due_date' => '2026-10-30', 'description' => 'Cement invoice', 'receipt_confirmed' => false,
        'lines' => [['inventory_id' => '', 'accounting_account_id' => $account->id, 'description' => 'Repairs', 'quantity' => '', 'amount' => '100.00']],
    ], $actor->id);

    expect(fn () => app(SupplierPurchaseService::class)->post($invoice->id, $actor->id))
        ->toThrow(ValidationException::class);
    expect($invoice->fresh()->status)->toBe('draft')
        ->and(DB::table('accounting_journals')->count())->toBe(0)
        ->and(DB::table('stock_movements')->whereNotNull('accounting_journal_id')->count())->toBe(0);
});

test('received mixed credit purchase posts equipment and AP while adding a weighted-average valued inventory receipt once', function () {
    $actor = grantEmployeeTestPermissions(Employee::create([
        'username' => 'purchase.'.uniqid(), 'password' => Hash::make('password'), 'first_name' => 'Purchase',
        'last_name' => 'Accountant', 'email' => uniqid().'@example.test',
    ]), ['accounting.prepare-supplier-purchases', 'accounting.post-supplier-purchases']);
    $this->actingAs($actor);
    $period = AccountingPostingPeriod::create([
        'book_key' => 'FCDC', 'fiscal_year' => 2026, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open',
    ]);
    $opening = AccountingJournal::create([
        'book_key' => 'FCDC', 'reference' => 'OPEN-TEST', 'source_type' => 'opening', 'source_id' => 'FCDC',
        'accounting_date' => '2026-07-01', 'posting_period_id' => $period->id, 'description' => 'Approved opening',
        'status' => 'draft', 'prepared_by' => $actor->id,
    ]);
    $valuation = OpeningInventoryValuation::create([
        'book_key' => 'FCDC', 'cutover_date' => '2026-07-01', 'evidence_reference' => 'Approved stock count',
        'status' => 'draft', 'prepared_by' => $actor->id,
    ]);
    $inventory = Inventory::create(['name' => 'Cement', 'category' => 'Cement', 'qty' => '10.00', 'unit' => 'bag', 'unit_cost' => '100.00', 'reorder_level' => '0']);
    OpeningInventoryValuationLine::forceCreate([
        'opening_inventory_valuation_id' => $valuation->id, 'inventory_id' => $inventory->id,
        'quantity' => '10.00', 'carrying_value_cents' => 100000,
    ]);
    $valuation->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()])->save();
    $opening->forceFill(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $actor->id, 'approved_at' => now(), 'approved_by' => $actor->id])->save();
    $accounts = [
        'inventory' => ['1200', 'Inventory', 'Asset', 'debit'],
        'accounts_payable' => ['2100', 'Accounts Payable', 'Liability', 'credit'],
        'equipment' => ['1600', 'Equipment', 'Asset', 'debit'],
    ];
    foreach ($accounts as $key => [$code, $name, $type, $balance]) {
        $account = AccountingAccount::create([
            'code' => $code, 'name' => $name, 'type' => $type, 'classification' => $key,
            'normal_balance' => $balance, 'is_active' => true, 'approved_at' => now(), 'approved_by' => $actor->id,
        ]);
        if ($key !== 'equipment') {
            AccountingPostingMapping::create([
                'source' => $key, 'accounting_account_id' => $account->id, 'approved_at' => now(), 'approved_by' => $actor->id,
            ]);
        } else {
            $equipment = $account;
        }
    }
    $supplier = Supplier::create(['code' => 'SUP-MIX', 'name' => 'Mixed Supplier', 'created_by' => $actor->id]);
    $invoice = app(SupplierPurchaseService::class)->saveDraft([
        'supplier_id' => $supplier->id, 'invoice_number' => 'MIX-1', 'recognition_date' => '2026-10-01',
        'due_date' => '2026-10-31', 'description' => 'Equipment and cement', 'receipt_confirmed' => true,
        'lines' => [
            ['inventory_id' => $inventory->id, 'accounting_account_id' => '', 'description' => 'Cement receipt', 'quantity' => '10.00', 'amount' => '2000.00'],
            ['inventory_id' => '', 'accounting_account_id' => $equipment->id, 'description' => 'Equipment', 'quantity' => '', 'amount' => '50000.00'],
        ],
    ], $actor->id);

    $payableAccount = AccountingAccount::query()->where('classification', 'accounts_payable')->firstOrFail();
    AccountingPostingMapping::query()->where('source', 'accounts_payable')->delete();
    expect(fn () => app(SupplierPurchaseService::class)->post($invoice->id, $actor->id))
        ->toThrow(ValidationException::class);
    expect($invoice->fresh()->status)->toBe('draft')
        ->and($inventory->fresh()->qty)->toBe('10.00')
        ->and(DB::table('accounting_journals')->where('source_type', 'supplier_purchase')->count())->toBe(0)
        ->and(DB::table('stock_movements')->where('source_reference', 'supplier_purchase:'.$invoice->id)->count())->toBe(0);
    AccountingPostingMapping::create([
        'source' => 'accounts_payable', 'accounting_account_id' => $payableAccount->id,
        'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    $conflict = AccountingJournal::create([
        'book_key' => 'FCDC', 'reference' => 'PUR-CONFLICT', 'source_type' => 'supplier_purchase',
        'source_id' => (string) $invoice->id, 'accounting_date' => '2026-10-01',
        'posting_period_id' => $period->id, 'description' => 'Conflicting source', 'status' => 'draft',
        'prepared_by' => $actor->id,
    ]);
    expect(fn () => app(SupplierPurchaseService::class)->post($invoice->id, $actor->id))
        ->toThrow(QueryException::class);
    expect($invoice->fresh()->status)->toBe('draft')
        ->and($inventory->fresh()->qty)->toBe('10.00')
        ->and(DB::table('stock_movements')->where('source_reference', 'supplier_purchase:'.$invoice->id)->count())->toBe(0);
    $conflict->delete();

    $posted = app(SupplierPurchaseService::class)->post($invoice->id, $actor->id);
    $receipt = DB::table('stock_movements')->where('source_reference', 'supplier_purchase:'.$invoice->id)->first();

    expect($posted->status)->toBe('posted')
        ->and((int) $posted->gross_amount_cents)->toBe(5200000)
        ->and($inventory->fresh()->qty)->toBe('20.00')
        ->and((int) $receipt->value_cents)->toBe(200000)
        ->and((int) $receipt->carrying_value_after_cents)->toBe(300000)
        ->and(DB::table('stock_movements')->where('source_reference', 'supplier_purchase:'.$invoice->id)->count())->toBe(1)
        ->and((int) DB::table('accounting_journal_lines')->where('accounting_journal_id', $posted->accounting_journal_id)->sum('debit_cents'))->toBe(5200000)
        ->and((int) DB::table('accounting_journal_lines')->where('accounting_journal_id', $posted->accounting_journal_id)->sum('credit_cents'))->toBe(5200000)
        ->and((int) DB::table('accounting_journal_lines')->where('accounting_account_id', $equipment->id)->value('debit_cents'))->toBe(5000000);
    expect(fn () => $posted->lines->first()->update(['description' => 'Changed after posting']))
        ->toThrow(DomainException::class);
});
test('supplier purchase register is available to accounting viewers and filters posted invoices', function () {
    $viewer = grantEmployeeTestPermissions(Employee::create([
        'username' => 'purchase.'.uniqid(), 'password' => Hash::make('password'), 'first_name' => 'Purchase',
        'last_name' => 'Viewer', 'email' => uniqid().'@example.test',
    ]), ['accounting.view']);

    $this->actingAs($viewer)->get(route('accounting.supplier-purchases'))
        ->assertOk()
        ->assertSee('Supplier Credit Purchases')
        ->assertSee('Supplier invoice register')
        ->assertSee('Status');
});
