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
use App\Services\Accounting\CashDisbursementService;
use App\Services\Accounting\SupplierPurchaseCorrectionService;
use App\Services\Inventory\RecordStockIn;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);
test('supplier purchase invoice composite index has a MySQL-safe explicit name', function () {
    $indexNames = collect(DB::getSchemaBuilder()->getIndexes('supplier_purchase_invoices'))
        ->pluck('name');

    expect($indexNames)->toContain('supplier_purchase_invoices_due_status_idx')
        ->and(strlen('supplier_purchase_invoices_due_status_idx'))->toBeLessThanOrEqual(64);
});


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
test('a fully paid PHP 50,000 purchase correction creates and clears a linked PHP 10,000 supplier refund receivable', function () {
    $actor = grantEmployeeTestPermissions(Employee::create([
        'username' => 'correct.'.fake()->unique()->numerify('####'),
        'password' => Hash::make('password'),
        'first_name' => 'Purchase',
        'last_name' => 'Corrector',
        'email' => fake()->unique()->safeEmail(),
    ]), [
        'accounting.view', 'accounting.prepare-supplier-purchases', 'accounting.post-supplier-purchases',
        'accounting.correct-supplier-purchases', 'accounting.post-supplier-refunds',
        'accounting.prepare-disbursements', 'accounting.post-disbursements',
    ]);
    $period = AccountingPostingPeriod::create([
        'book_key' => 'FCDC', 'fiscal_year' => now('Asia/Manila')->year,
        'starts_on' => now('Asia/Manila')->startOfYear()->toDateString(),
        'ends_on' => now('Asia/Manila')->endOfYear()->toDateString(), 'status' => 'open',
    ]);
    AccountingJournal::create([
        'book_key' => 'FCDC', 'reference' => 'OPEN-CORR-'.$actor->id, 'source_type' => 'opening',
        'source_id' => 'FCDC', 'accounting_date' => $period->starts_on, 'posting_period_id' => $period->id,
        'description' => 'Approved cutover', 'status' => 'posted', 'prepared_by' => $actor->id,
        'posted_by' => $actor->id, 'posted_at' => now(),
    ]);
    $ap = AccountingAccount::create([
        'code' => '2C'.$actor->id, 'name' => 'Accounts Payable', 'type' => 'Liability',
        'classification' => 'accounts_payable', 'normal_balance' => 'credit', 'is_active' => true,
        'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    $expense = AccountingAccount::create([
        'code' => '6C'.$actor->id, 'name' => 'Repairs Expense', 'type' => 'Expense',
        'classification' => 'operating_expense', 'normal_balance' => 'debit', 'is_active' => true,
        'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    $bank = AccountingAccount::create([
        'code' => '1C'.$actor->id, 'name' => 'Operating Bank', 'type' => 'Asset',
        'classification' => 'bank', 'normal_balance' => 'debit', 'is_active' => true,
        'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    $receivable = AccountingAccount::create([
        'code' => 'AR'.$actor->id, 'name' => 'Accounts Receivable', 'type' => 'Asset',
        'classification' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_active' => true,
        'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    AccountingPostingMapping::create([
        'source' => 'accounts_payable', 'accounting_account_id' => $ap->id,
        'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    $supplier = Supplier::create(['code' => 'COR-'.$actor->id, 'name' => 'Correction Supplier', 'created_by' => $actor->id]);
    $invoice = app(SupplierPurchaseService::class)->saveDraft([
        'supplier_id' => $supplier->id, 'invoice_number' => 'COR-INV-'.$actor->id,
        'recognition_date' => now('Asia/Manila')->toDateString(), 'due_date' => now('Asia/Manila')->toDateString(),
        'description' => 'Purchase to correct', 'receipt_confirmed' => true,
        'lines' => [['inventory_id' => '', 'accounting_account_id' => $expense->id, 'description' => 'Repairs', 'quantity' => '', 'amount' => '50000.00']],
    ], $actor->id);
    $posted = app(SupplierPurchaseService::class)->post($invoice->id, $actor->id);
    $payment = app(CashDisbursementService::class)->saveDraft([
        'payee' => $supplier->name, 'supplierId' => (string) $supplier->id,
        'paymentDate' => now('Asia/Manila')->toDateString(), 'method' => 'Bank Transfer',
        'moneyAccountId' => (string) $bank->id, 'reference' => 'PAY-COR-'.$actor->id,
        'checkNumber' => '', 'description' => 'Settle corrected purchase',
        'evidenceReference' => 'Bank advice COR-'.$actor->id, 'amount' => '50000.00',
        'allocations' => [['invoice_id' => 'purchase:'.$invoice->id, 'description' => 'Invoice settlement', 'amount' => '50000.00']],
    ], $actor->id);
    $payment = app(CashDisbursementService::class)->post($payment->id, $actor->id);

    expect(fn () => app(SupplierPurchaseCorrectionService::class)->correct($invoice->id, [
        'reason' => 'Supplier issued corrected invoice',
        'allocations' => [['line_id' => $invoice->lines->first()->id, 'corrected_amount_cents' => 5_000_000]],
    ], $actor->id))->toThrow(ValidationException::class);
    expect($invoice->fresh()->gross_amount_cents)->toBe(5_000_000)
        ->and($payment->fresh()->status)->toBe('posted')
        ->and(DB::table('supplier_purchase_corrections')->count())->toBe(0)
        ->and(DB::table('accounting_journals')->where('source_type', 'supplier_purchase_correction')->count())->toBe(0);

    $nextCorrectionId = (int) DB::table('supplier_purchase_corrections')->max('id') + 1;
    $collisionInvoice = app(SupplierPurchaseService::class)->saveDraft([
        'supplier_id' => $supplier->id, 'invoice_number' => 'COR-INV-'.$actor->id.'-C'.$nextCorrectionId,
        'recognition_date' => now('Asia/Manila')->toDateString(), 'due_date' => now('Asia/Manila')->toDateString(),
        'description' => 'Existing supplier invoice matching the generated correction identity', 'receipt_confirmed' => true,
        'lines' => [['inventory_id' => '', 'accounting_account_id' => $expense->id, 'description' => 'Repairs', 'quantity' => '', 'amount' => '100.00']],
    ], $actor->id);

    $correction = app(SupplierPurchaseCorrectionService::class)->correct($invoice->id, [
        'reason' => 'Supplier issued corrected invoice',
        'allocations' => [['line_id' => $invoice->lines->first()->id, 'corrected_amount_cents' => 4_000_000]],
    ], $actor->id);
    $replacement = $correction->replacementInvoice;

    expect($invoice->fresh()->gross_amount_cents)->toBe(5_000_000)
        ->and($invoice->fresh()->paidAmountCents())->toBe(5_000_000)
        ->and($invoice->fresh()->outstandingAmountCents())->toBe(0)
        ->and($invoice->fresh()->supplierRefundDueCents())->toBe(1_000_000)
        ->and($replacement->gross_amount_cents)->toBe(4_000_000)
        ->and($replacement->correction_of_id)->toBe($invoice->id)
        ->and($replacement->invoice_number)->toBe($collisionInvoice->invoice_number.'-2')
        ->and($payment->fresh()->lines)->toHaveCount(1)
        ->and((int) $correction->journal->lines->firstWhere('accounting_account_id', $receivable->id)->debit_cents)->toBe(1_000_000);

    $receipt = app(SupplierPurchaseCorrectionService::class)->receiveRefund($correction->id, [
        'amount_cents' => 1_000_000, 'money_account_id' => $bank->id,
        'reference' => 'REF-COR-'.$actor->id, 'evidence_reference' => 'Supplier bank refund',
        'receipt_date' => now('Asia/Manila')->toDateString(),
    ], $actor->id);

    expect($receipt->amount_cents)->toBe(1_000_000)
        ->and($invoice->fresh()->supplierRefundDueCents())->toBe(0)
        ->and($receipt->journal->correction_of_id)->toBe($correction->journal_id)
        ->and((int) $receipt->journal->lines->firstWhere('accounting_account_id', $bank->id)->debit_cents)->toBe(1_000_000);
    expect($invoice->fresh()->accounting_journal_id)->toBe($posted->accounting_journal_id);

    $secondCorrection = app(SupplierPurchaseCorrectionService::class)->correct($replacement->id, [
        'reason' => 'Revised corrected purchase amount',
        'allocations' => [['line_id' => $replacement->lines->first()->id, 'corrected_amount_cents' => 4_500_000]],
    ], $actor->id);

    expect($secondCorrection->replacementInvoice->correction_of_id)->toBe($replacement->id)
        ->and($invoice->fresh()->activeCorrectedAmountCents())->toBe(4_500_000)
        ->and($invoice->fresh()->supplierRefundDueCents())->toBe(0)
        ->and($invoice->fresh()->outstandingAmountCents())->toBe(500_000)
        ->and($invoice->fresh()->isActiveCorrectionIdentity())->toBeFalse()
        ->and($replacement->fresh()->isActiveCorrectionIdentity())->toBeFalse()
        ->and($secondCorrection->replacementInvoice->fresh()->isActiveCorrectionIdentity())->toBeTrue()
        ->and((int) $secondCorrection->journal->lines->firstWhere('accounting_account_id', $ap->id)->credit_cents)->toBe(500_000);
    Livewire::actingAs($actor)->test(\App\Livewire\Accounting\SupplierPurchases::class)
        ->assertSee('Correct purchase')
        ->call('showInvoice', $secondCorrection->replacementInvoice->id)
        ->assertSee('Financial correction and supplier refund history')
        ->assertSee('PAY-COR-'.$actor->id)
        ->assertSee('REF-COR-'.$actor->id)
        ->assertSee($secondCorrection->replacementInvoice->invoice_number)
        ->assertSee('Supplier refund receivable outstanding');
});
test('purchase correction allocates reviewed cost between remaining and consumed stock without changing quantity', function () {
    $actor = grantEmployeeTestPermissions(Employee::create([
        'username' => 'stockcorrect.'.fake()->unique()->numerify('####'),
        'password' => Hash::make('password'), 'first_name' => 'Stock', 'last_name' => 'Corrector',
        'email' => fake()->unique()->safeEmail(),
    ]), [
        'accounting.prepare-supplier-purchases', 'accounting.post-supplier-purchases',
        'accounting.correct-supplier-purchases', 'accounting.post-disbursements',
        'accounting.prepare-disbursements', 'inventory.movements.record', 'inventory.valuation.approve',
    ]);
    $period = AccountingPostingPeriod::create([
        'book_key' => 'FCDC', 'fiscal_year' => now('Asia/Manila')->year,
        'starts_on' => now('Asia/Manila')->startOfYear()->toDateString(),
        'ends_on' => now('Asia/Manila')->endOfYear()->toDateString(), 'status' => 'open',
    ]);
    $openingJournal = AccountingJournal::create([
        'book_key' => 'FCDC', 'reference' => 'OPEN-STOCK-CORR-'.$actor->id, 'source_type' => 'opening',
        'source_id' => 'FCDC', 'accounting_date' => $period->starts_on, 'posting_period_id' => $period->id,
        'description' => 'Approved cutover', 'status' => 'draft', 'prepared_by' => $actor->id,
    ]);
    $inventoryAccount = AccountingAccount::create([
        'code' => '12'.$actor->id, 'name' => 'Inventory', 'type' => 'Asset',
        'classification' => 'inventory', 'normal_balance' => 'debit', 'is_active' => true,
        'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    $ap = AccountingAccount::create([
        'code' => '21'.$actor->id, 'name' => 'Accounts Payable', 'type' => 'Liability',
        'classification' => 'accounts_payable', 'normal_balance' => 'credit', 'is_active' => true,
        'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    $adjustment = AccountingAccount::create([
        'code' => '62'.$actor->id, 'name' => 'Inventory Cost Adjustment', 'type' => 'Expense',
        'classification' => 'operating_expense', 'normal_balance' => 'debit', 'is_active' => true,
        'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    foreach ([['inventory', $inventoryAccount], ['accounts_payable', $ap], ['adjustment', $adjustment]] as [$source, $account]) {
        AccountingPostingMapping::create([
            'source' => $source, 'accounting_account_id' => $account->id,
            'approved_at' => now(), 'approved_by' => $actor->id,
        ]);
    }
    $item = Inventory::create([
        'name' => 'Correction stock', 'category' => 'Materials', 'qty' => '0.00',
        'unit' => 'bag', 'unit_cost' => '0.00', 'reorder_level' => '0',
    ]);
    $valuation = OpeningInventoryValuation::create([
        'book_key' => 'FCDC', 'cutover_date' => $period->starts_on,
        'evidence_reference' => 'Opening stock count', 'status' => 'draft', 'prepared_by' => $actor->id,
    ]);
    OpeningInventoryValuationLine::forceCreate([
        'opening_inventory_valuation_id' => $valuation->id, 'inventory_id' => $item->id,
        'quantity' => '0.00', 'carrying_value_cents' => 0,
    ]);
    $valuation->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()])->save();
    $openingJournal->forceFill([
        'status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now(),
        'approved_by' => $actor->id, 'approved_at' => now(),
    ])->save();
    $supplier = Supplier::create(['code' => 'STK-'.$actor->id, 'name' => 'Stock Supplier', 'created_by' => $actor->id]);
    $invoice = app(SupplierPurchaseService::class)->saveDraft([
        'supplier_id' => $supplier->id, 'invoice_number' => 'STK-INV-'.$actor->id,
        'recognition_date' => now('Asia/Manila')->toDateString(), 'due_date' => now('Asia/Manila')->toDateString(),
        'description' => 'Ten bags', 'receipt_confirmed' => true,
        'lines' => [['inventory_id' => $item->id, 'accounting_account_id' => '', 'description' => 'Ten bags', 'quantity' => '10.00', 'amount' => '50000.00']],
    ], $actor->id);
    app(SupplierPurchaseService::class)->post($invoice->id, $actor->id);
    app(\App\Services\Inventory\RecordValuedStockMovement::class)->handle(
        $item->id, 'stock_out', '2.00', 'damage_loss', 'DAMAGED-CORR-'.$actor->id,
        now('Asia/Manila')->toDateString(), $actor->id, null, 'Consumed stock before price correction',
    );

    expect($item->fresh()->qty)->toBe('8.00')
        ->and($item->fresh()->carrying_value_cents)->toBe(4_000_000);
    $correction = app(SupplierPurchaseCorrectionService::class)->correct($invoice->id, [
        'reason' => 'Supplier credited the purchase price',
        'allocations' => [[
            'line_id' => $invoice->lines->first()->id, 'corrected_amount_cents' => 4_000_000,
            'remaining_inventory_cents' => 800_000, 'consumed_cost_cents' => 200_000,
            'consumed_accounting_account_id' => $adjustment->id,
        ]],
    ], $actor->id);

    expect($item->fresh()->qty)->toBe('8.00')
        ->and($item->fresh()->carrying_value_cents)->toBe(3_200_000)
        ->and((int) DB::table('supplier_purchase_correction_lines')->where('supplier_purchase_correction_id', $correction->id)->value('remaining_inventory_cents'))->toBe(800_000)
        ->and((int) DB::table('supplier_purchase_correction_lines')->where('supplier_purchase_correction_id', $correction->id)->value('consumed_cost_cents'))->toBe(200_000)
        ->and((int) $correction->journal->lines->firstWhere('accounting_account_id', $inventoryAccount->id)->credit_cents)->toBe(800_000)
        ->and((int) $correction->journal->lines->firstWhere('accounting_account_id', $adjustment->id)->credit_cents)->toBe(200_000)
        ->and(DB::table('stock_movements')->where('source_reference', 'supplier_purchase:'.$invoice->id)->count())->toBe(1)
        ->and($openingJournal->fresh()->status)->toBe('posted');
});
test('direct supplier purchases preserve the disbursement and post a linked refund receivable', function () {
    $actor = grantEmployeeTestPermissions(Employee::create([
        'username' => 'directcorrect.'.fake()->unique()->numerify('####'),
        'password' => Hash::make('password'), 'first_name' => 'Direct', 'last_name' => 'Corrector',
        'email' => fake()->unique()->safeEmail(),
    ]), [
        'accounting.prepare-disbursements', 'accounting.post-disbursements',
        'accounting.correct-supplier-purchases', 'accounting.post-supplier-refunds',
    ]);
    $period = AccountingPostingPeriod::create([
        'book_key' => 'FCDC', 'fiscal_year' => now('Asia/Manila')->year,
        'starts_on' => now('Asia/Manila')->startOfYear()->toDateString(),
        'ends_on' => now('Asia/Manila')->endOfYear()->toDateString(), 'status' => 'open',
    ]);
    AccountingJournal::create([
        'book_key' => 'FCDC', 'reference' => 'OPEN-DIRECT-CORR-'.$actor->id, 'source_type' => 'opening',
        'source_id' => 'FCDC', 'accounting_date' => $period->starts_on, 'posting_period_id' => $period->id,
        'description' => 'Approved cutover', 'status' => 'posted', 'prepared_by' => $actor->id,
        'posted_by' => $actor->id, 'posted_at' => now(),
    ]);
    $expense = AccountingAccount::create([
        'code' => '6D'.$actor->id, 'name' => 'Direct Purchase Expense', 'type' => 'Expense',
        'classification' => 'operating_expense', 'normal_balance' => 'debit', 'is_active' => true,
        'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    $bank = AccountingAccount::create([
        'code' => '1D'.$actor->id, 'name' => 'Direct Purchase Bank', 'type' => 'Asset',
        'classification' => 'bank', 'normal_balance' => 'debit', 'is_active' => true,
        'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    $receivable = AccountingAccount::create([
        'code' => 'AR-D'.$actor->id, 'name' => 'Accounts Receivable', 'type' => 'Asset',
        'classification' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_active' => true,
        'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    $supplier = Supplier::create(['code' => 'DIR-'.$actor->id, 'name' => 'Direct Purchase Supplier', 'created_by' => $actor->id]);
    $payment = app(CashDisbursementService::class)->saveDraft([
        'payee' => $supplier->name, 'supplierId' => null, 'paymentDate' => now('Asia/Manila')->toDateString(),
        'method' => 'Bank Transfer', 'moneyAccountId' => (string) $bank->id,
        'reference' => 'DIRECT-PUR-'.$actor->id, 'checkNumber' => '',
        'description' => 'Direct paid purchase', 'evidenceReference' => 'Supplier invoice DIR-'.$actor->id,
        'amount' => '50000.00',
        'allocations' => [['accounting_account_id' => (string) $expense->id, 'description' => 'Direct equipment purchase', 'amount' => '50000.00']],
    ], $actor->id);
    $payment = app(CashDisbursementService::class)->post($payment->id, $actor->id);
    $correction = app(SupplierPurchaseCorrectionService::class)->correctDirectPurchase($payment->id, [
        'supplier_id' => $supplier->id, 'reason' => 'Supplier issued a direct-purchase credit',
        'allocations' => [[
            'line_id' => $payment->lines->first()->id, 'corrected_amount_cents' => 4_000_000,
        ]],
    ], $actor->id);

    expect($correction->source_type)->toBe('direct_purchase')
        ->and($correction->refund_due_cents)->toBe(1_000_000)
        ->and($payment->fresh()->status)->toBe('posted')
        ->and($payment->fresh()->amount_cents)->toBe(5_000_000)
        ->and((int) $correction->journal->lines->firstWhere('accounting_account_id', $receivable->id)->debit_cents)->toBe(1_000_000)
        ->and((int) $correction->journal->lines->firstWhere('accounting_account_id', $expense->id)->credit_cents)->toBe(1_000_000);

    $receipt = app(SupplierPurchaseCorrectionService::class)->receiveRefund($correction->id, [
        'amount_cents' => 1_000_000, 'money_account_id' => $bank->id,
        'reference' => 'DIRECT-REF-'.$actor->id, 'evidence_reference' => 'Supplier credit bank receipt',
        'receipt_date' => now('Asia/Manila')->toDateString(),
    ], $actor->id);

    expect($receipt->amount_cents)->toBe(1_000_000)
        ->and((int) DB::table('supplier_refund_receipts')->where('supplier_purchase_correction_id', $correction->id)->sum('amount_cents'))->toBe(1_000_000)
        ->and($payment->fresh()->journal_id)->not->toBe($correction->journal_id);
    $nextCorrection = app(SupplierPurchaseCorrectionService::class)->correctDirectPurchase($payment->id, [
        'supplier_id' => $supplier->id, 'reason' => 'Additional direct-purchase credit',
        'allocations' => [[
            'line_id' => $payment->lines->first()->id, 'corrected_amount_cents' => 3_500_000,
        ]],
    ], $actor->id);

    expect($nextCorrection->original_amount_cents)->toBe(4_000_000)
        ->and($nextCorrection->refund_due_cents)->toBe(500_000)
        ->and($nextCorrection->journal->correction_of_id)->toBe($correction->journal_id)
        ->and((int) $nextCorrection->journal->lines->firstWhere('accounting_account_id', $receivable->id)->debit_cents)->toBe(500_000);
    expect(fn () => app(CashDisbursementService::class)->reverse($payment->id, 'Attempt reversal after correction', $actor->id))
        ->toThrow(ValidationException::class);
    $reversedPayment = app(CashDisbursementService::class)->saveDraft([
        'payee' => $supplier->name, 'supplierId' => null, 'paymentDate' => now('Asia/Manila')->toDateString(),
        'method' => 'Bank Transfer', 'moneyAccountId' => (string) $bank->id,
        'reference' => 'DIRECT-REV-'.$actor->id, 'checkNumber' => '',
        'description' => 'Reversed direct purchase', 'evidenceReference' => 'Supplier invoice reversed',
        'amount' => '1000.00',
        'allocations' => [['accounting_account_id' => (string) $expense->id, 'description' => 'Direct purchase', 'amount' => '1000.00']],
    ], $actor->id);
    $reversedPayment = app(CashDisbursementService::class)->post($reversedPayment->id, $actor->id);
    app(CashDisbursementService::class)->reverse($reversedPayment->id, 'Supplier canceled the direct purchase', $actor->id);
    expect(fn () => app(SupplierPurchaseCorrectionService::class)->correctDirectPurchase($reversedPayment->id, [
        'supplier_id' => $supplier->id, 'reason' => 'Attempt correction after reversal',
        'allocations' => [[
            'line_id' => $reversedPayment->lines->first()->id, 'corrected_amount_cents' => 90_000,
        ]],
    ], $actor->id))->toThrow(ValidationException::class);
});
