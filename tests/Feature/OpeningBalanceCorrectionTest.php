<?php

use App\Livewire\Accounting\FinancialStatements;
use App\Livewire\Accounting\GeneralLedger;
use App\Livewire\Accounting\OpeningBooks;
use App\Livewire\Accounting\TrialBalance;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\Supplier;
use App\Models\SupplierOpeningInvoice;
use App\Services\Accounting\AccountingPositionSchedules;
use App\Services\Accounting\InterveningSourceReconciliation;
use App\Services\Accounting\OpeningBalanceCorrectionService;
use App\Services\Accounting\OpeningBooksService;
use App\Services\Accounting\SupplierPayablesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function openingCorrectionEmployee(array $permissions): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'opening-correction.'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => Hash::make('secret-password'),
    ]), $permissions);
}

function openingCorrectionAccount(string $code, string $classification, string $type, string $normal): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => ucfirst(str_replace('_', ' ', $classification)),
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $normal,
        'is_active' => true,
        'approved_at' => now(),
        'approved_by' => auth()->id(),
    ]);
}

function postSimpleOpeningJournal(AccountingAccount $debit, AccountingAccount $credit, Employee $actor, string $amount = '100.00', ?string $date = null): AccountingJournal
{
    $books = app(OpeningBooksService::class);
    $books->saveOpening($date ?? now('Asia/Manila')->toDateString(), [
        ['accountId' => (string) $debit->id, 'debit' => $amount, 'credit' => ''],
        ['accountId' => (string) $credit->id, 'debit' => '', 'credit' => $amount],
    ], $actor->id);

    return $books->approveOpening($actor->id);
}

test('opening journal corrections require independent review and post a linked balanced journal without changing the original', function () {
    $requester = openingCorrectionEmployee(['accounting.view', 'accounting.maintain-opening-books']);
    $reviewer = openingCorrectionEmployee(['accounting.view', 'accounting.approve-opening-books']);
    $cash = openingCorrectionAccount('OC-100', 'cash', 'Asset', 'debit');
    $capital = openingCorrectionAccount('OC-300', 'capital', 'Equity', 'credit');
    $this->actingAs($requester);
    $original = postSimpleOpeningJournal($cash, $capital, $requester, '100.00', now('Asia/Manila')->startOfYear()->toDateString());
    $originalLines = $original->lines()->get()->map(fn ($line) => [$line->accounting_account_id, $line->debit_cents, $line->credit_cents])->all();
    $service = app(OpeningBalanceCorrectionService::class);
    $request = $service->request([
        'type' => 'journal', 'reason' => 'Documented opening cash-count correction',
        'details' => ['lines' => [
            ['accountId' => (string) $cash->id, 'debit' => '10.00', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '10.00'],
        ]],
    ], $requester);

    expect(fn () => $service->approve($request->id, 'review', $requester))->toThrow(ValidationException::class);
    expect($request->fresh()->status)->toBe('pending_review')
        ->and($original->fresh()->status)->toBe('posted');

    $service->approve($request->id, 'Evidence checked', $reviewer);
    $journal = $service->post($request->id, $reviewer);
    expect($request->fresh()->status)->toBe('posted')
        ->and($journal->correction_of_id)->toBe($original->id)
        ->and($journal->status)->toBe('posted')
        ->and($journal->lines->sum('debit_cents'))->toBe($journal->lines->sum('credit_cents'))
        ->and($original->fresh()->lines()->get()->map(fn ($line) => [$line->accounting_account_id, $line->debit_cents, $line->credit_cents])->all())->toBe($originalLines);

    $this->actingAs($reviewer);
    Livewire::test(GeneralLedger::class)->set('accountId', (string) $cash->id)->assertSee($journal->reference);
    Livewire::test(TrialBalance::class)
        ->set('fromDate', now('Asia/Manila')->startOfMonth()->toDateString())
        ->set('toDate', now('Asia/Manila')->toDateString())
        ->assertSee('10.00');
    Livewire::test(FinancialStatements::class)
        ->set('statementType', 'balance-sheet')
        ->assertSee('110.00');
});

test('opening inventory corrections append schedule adjustments and preserve the approved valuation lines', function () {
    $requester = openingCorrectionEmployee(['accounting.view', 'accounting.maintain-opening-books']);
    $reviewer = openingCorrectionEmployee(['accounting.view', 'accounting.approve-opening-books']);
    $item = Inventory::create([
        'name' => 'Correction test material', 'category' => 'Materials', 'qty' => '2.00', 'unit' => 'piece',
        'unit_cost' => '10.00', 'reorder_level' => '0.00',
    ]);
    $this->actingAs($reviewer);
    $accounts = setupPosAccountingBooks([$item]);
    $opening = AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')->firstOrFail();
    $originalValue = (int) DB::table('opening_inventory_valuation_lines')->where('inventory_id', $item->id)->value('carrying_value_cents');
    $this->actingAs($requester);
    $request = app(OpeningBalanceCorrectionService::class)->request([
        'type' => 'inventory', 'reason' => 'Approved supplier evidence supports revised opening carrying value',
        'details' => ['counterAccountId' => (string) $accounts['capital']->id, 'lines' => [['inventoryId' => (string) $item->id, 'value' => '30.00']]],
    ], $requester);
    app(OpeningBalanceCorrectionService::class)->approve($request->id, 'Evidence checked', $reviewer);
    app(OpeningBalanceCorrectionService::class)->post($request->id, $reviewer);

    expect((int) DB::table('opening_inventory_valuation_lines')->where('inventory_id', $item->id)->value('carrying_value_cents'))->toBe($originalValue)
        ->and((int) $item->fresh()->carrying_value_cents)->toBe(3000)
        ->and(app(AccountingPositionSchedules::class)->inventoryValueAsOf(now('Asia/Manila')->toDateString(), $opening->accounting_date->toDateString()))->toBe([3000, null])
        ->and(app(InterveningSourceReconciliation::class)->sources()->firstWhere('reference', 'OPEN-CORR-'.$request->id.'-'.$item->id)['status'])->toBe('matched');
});

test('supplier opening schedule corrections preserve posted invoices and affect the AP schedule after posting', function () {
    $requester = openingCorrectionEmployee(['accounting.view', 'accounting.maintain-opening-books', 'accounting.maintain-suppliers']);
    $reviewer = openingCorrectionEmployee(['accounting.view', 'accounting.approve-opening-books']);
    $cash = openingCorrectionAccount('OC-110', 'cash', 'Asset', 'debit');
    $ap = openingCorrectionAccount('OC-200', 'accounts_payable', 'Liability', 'credit');
    $counterpart = openingCorrectionAccount('OC-510', 'operating_expense', 'Expense', 'debit');
    $supplier = Supplier::create(['code' => 'OC-SUP', 'name' => 'Correction Supplier', 'created_by' => $requester->id]);
    $this->actingAs($requester);
    app(SupplierPayablesService::class)->saveOpeningInvoice([
        'supplier_id' => $supplier->id, 'invoice_number' => 'OC-INV-1',
        'recognition_date' => now('Asia/Manila')->toDateString(), 'due_date' => now('Asia/Manila')->addMonth()->toDateString(),
        'amount' => '100.00', 'description' => 'Opening payable', 'terms' => 'Net 30',
    ], $requester->id);
    $opening = postSimpleOpeningJournal($cash, $ap, $requester);
    $invoice = SupplierOpeningInvoice::query()->where('invoice_number', 'OC-INV-1')->firstOrFail();
    $invoice->refresh();
    $this->actingAs($requester);
    $request = app(OpeningBalanceCorrectionService::class)->request([
        'type' => 'supplier_ap', 'reason' => 'Supplier statement confirms additional amount due',
        'details' => ['counterAccountId' => (string) $counterpart->id, 'lines' => [['invoiceId' => (string) $invoice->id, 'amount' => '120.00']]],
    ], $requester);
    app(OpeningBalanceCorrectionService::class)->approve($request->id, 'Statement matched', $reviewer);
    app(OpeningBalanceCorrectionService::class)->post($request->id, $reviewer);

    expect((int) $invoice->fresh()->amount_cents)->toBe(10000)
        ->and($invoice->fresh()->effectiveAmountCents())->toBe(12000)
        ->and(app(AccountingPositionSchedules::class)->accountsPayableAsOf(now('Asia/Manila')->toDateString()))->toBe(12000)
        ->and($opening->fresh()->status)->toBe('posted');
});

test('cutover UI shows correction request controls only to authorized preparers', function () {
    $preparer = openingCorrectionEmployee(['accounting.view', 'accounting.maintain-opening-books']);
    $viewer = openingCorrectionEmployee(['accounting.view']);
    $cash = openingCorrectionAccount('OC-120', 'cash', 'Asset', 'debit');
    $capital = openingCorrectionAccount('OC-320', 'capital', 'Equity', 'credit');
    $this->actingAs($preparer);
    postSimpleOpeningJournal($cash, $capital, $preparer);
    Livewire::test(OpeningBooks::class)->set('currentStep', 3)->assertSee('Submit correction request');
    $this->actingAs($viewer);
    Livewire::test(OpeningBooks::class)->set('currentStep', 3)->assertDontSee('Submit correction request');
});

test('a reviewer can reject a correction with a reason without creating accounting entries', function () {
    $requester = openingCorrectionEmployee(['accounting.view', 'accounting.maintain-opening-books']);
    $reviewer = openingCorrectionEmployee(['accounting.view', 'accounting.approve-opening-books']);
    $cash = openingCorrectionAccount('OC-130', 'cash', 'Asset', 'debit');
    $capital = openingCorrectionAccount('OC-330', 'capital', 'Equity', 'credit');
    $this->actingAs($requester);
    $opening = postSimpleOpeningJournal($cash, $capital, $requester);
    $journalCount = AccountingJournal::query()->count();
    $request = app(OpeningBalanceCorrectionService::class)->request([
        'type' => 'journal', 'reason' => 'Correction request to reject',
        'details' => ['lines' => [
            ['accountId' => (string) $cash->id, 'debit' => '5.00', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '5.00'],
        ]],
    ], $requester);
    $this->actingAs($reviewer);
    app(OpeningBalanceCorrectionService::class)->reject($request->id, 'Supporting evidence is insufficient.', $reviewer);

    expect($request->fresh()->status)->toBe('rejected')
        ->and($request->fresh()->review_note)->toBe('Supporting evidence is insufficient.')
        ->and(AccountingJournal::query()->count())->toBe($journalCount)
        ->and($opening->fresh()->status)->toBe('posted');
});
