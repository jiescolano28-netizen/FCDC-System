<?php

use App\Livewire\Accounting\AccountsPayable;
use App\Livewire\Accounting\CashDisbursements;
use App\Livewire\Accounting\SupplierPurchases;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPostingMapping;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\SupplierOpeningInvoice;
use App\Models\SupplierPurchaseInvoice;
use App\Services\Accounting\CashDisbursementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function payableSettlementBooks(): array
{
    $actor = grantEmployeeTestPermissions(Employee::create([
        'username' => 'settlement.'.fake()->unique()->numerify('####'),
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('settlement-password'),
    ]), ['accounting.view', 'accounting.prepare-disbursements', 'accounting.post-disbursements']);
    $cash = settlementAccount('settlement-cash', 'Operating Bank', 'Asset', 'bank', $actor);
    $ap = settlementAccount('settlement-ap', 'Accounts Payable', 'Liability', 'accounts_payable', $actor);
    AccountingPostingMapping::create([
        'source' => 'accounts_payable', 'accounting_account_id' => $ap->id,
        'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
    $period = AccountingPostingPeriod::create([
        'book_key' => 'FCDC', 'fiscal_year' => now('Asia/Manila')->year,
        'starts_on' => now('Asia/Manila')->startOfYear()->toDateString(),
        'ends_on' => now('Asia/Manila')->endOfYear()->toDateString(), 'status' => 'open',
    ]);
    AccountingJournal::create([
        'book_key' => 'FCDC', 'reference' => 'OPEN-SETTLEMENT', 'source_type' => 'opening',
        'source_id' => 'FCDC', 'accounting_date' => $period->starts_on, 'posting_period_id' => $period->id,
        'description' => 'Approved cutover', 'status' => 'posted', 'prepared_by' => $actor->id,
        'posted_by' => $actor->id, 'posted_at' => now(),
    ]);
    $supplier = Supplier::create(['code' => 'SET-1', 'name' => 'Settlement Supplier', 'created_by' => $actor->id]);

    return [$actor, $cash, $ap, $supplier];
}

function settlementAccount(string $code, string $name, string $type, string $classification, Employee $actor): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code, 'name' => $name, 'type' => $type, 'classification' => $classification,
        'normal_balance' => $type === 'Liability' ? 'credit' : 'debit', 'is_active' => true,
        'approved_at' => now(), 'approved_by' => $actor->id,
    ]);
}

function payableInvoice(Supplier $supplier, Employee $actor, string $number, int $amount, bool $alreadyDue = false): SupplierPurchaseInvoice
{
    return SupplierPurchaseInvoice::create([
        'supplier_id' => $supplier->id, 'supplier_code_snapshot' => $supplier->code,
        'supplier_name_snapshot' => $supplier->name, 'invoice_number' => $number,
        'invoice_number_normalized' => strtoupper($number), 'recognition_date' => now('Asia/Manila')->toDateString(),
        'due_date' => now('Asia/Manila')->addDays($alreadyDue ? -1 : 5)->toDateString(), 'gross_amount_cents' => $amount,
        'description' => 'Posted supplier invoice', 'receipt_confirmed' => true, 'status' => 'posted',
        'prepared_by' => $actor->id, 'posted_by' => $actor->id, 'posted_at' => now(),
    ]);
}

function supplierPaymentInput(AccountingAccount $cash, Supplier $supplier, array $allocations, string $amount): array
{
    return [
        'payee' => $supplier->name, 'supplierId' => (string) $supplier->id,
        'paymentDate' => now('Asia/Manila')->toDateString(), 'method' => 'Bank Transfer',
        'moneyAccountId' => (string) $cash->id, 'reference' => 'PAY-'.fake()->unique()->numerify('####'),
        'checkNumber' => '', 'description' => 'Invoice settlement', 'evidenceReference' => 'Bank advice BA-1',
        'amount' => $amount, 'allocations' => $allocations,
    ];
}

test('a PHP 35,000 supplier settlement allocates across invoices, posts AP against Bank, then reversal restores balances', function () {
    [$actor, $cash, $ap, $supplier] = payableSettlementBooks();
    $first = payableInvoice($supplier, $actor, 'INV-30', 3_000_000);
    $second = payableInvoice($supplier, $actor, 'INV-20', 2_000_000, true);
    $service = app(CashDisbursementService::class);
    $draft = $service->saveDraft(supplierPaymentInput($cash, $supplier, [
        ['invoice_id' => 'purchase:'.$first->id, 'description' => 'INV-30 settlement', 'amount' => '30000.00'],
        ['invoice_id' => 'purchase:'.$second->id, 'description' => 'INV-20 settlement', 'amount' => '5000.00'],
    ], '35000.00'), $actor->id);

    expect($draft->status)->toBe('draft')
        ->and($first->fresh()->outstandingAmountCents())->toBe(3_000_000)
        ->and(AccountingJournal::where('source_type', 'cash_disbursement')->count())->toBe(0);

    $posted = $service->post($draft->id, $actor->id);
    expect($posted->journal->lines->where('accounting_account_id', $ap->id)->sum('debit_cents'))->toBe(3_500_000)
        ->and($posted->journal->lines->where('accounting_account_id', $cash->id)->sum('credit_cents'))->toBe(3_500_000)
        ->and($first->fresh()->outstandingAmountCents())->toBe(0)
        ->and($first->fresh()->payableStatus())->toBe('Paid')
        ->and($second->fresh()->outstandingAmountCents())->toBe(1_500_000)
        ->and($second->fresh()->payableStatus())->toBe('Partially paid')
        ->and($second->fresh()->isOverdueOn(now('Asia/Manila')->toDateString()))->toBeTrue();
    $this->actingAs($actor);
    Livewire::test(SupplierPurchases::class)
        ->assertSee('Partially paid')
        ->assertSee('15,000.00')
        ->call('showInvoice', $first->id)
        ->assertSee($posted->reference);
    Livewire::test(CashDisbursements::class)
        ->set('search', 'INV-20')
        ->assertSee($posted->reference);

    $reversal = $service->reverse($posted->id, 'Bank returned payment', $actor->id);
    expect($reversal->reversal_of_id)->toBe($posted->id)
        ->and($reversal->correction_reason)->toBe('Bank returned payment')
        ->and($reversal->lines)->toHaveCount(2)
        ->and($first->fresh()->outstandingAmountCents())->toBe(3_000_000)
        ->and($second->fresh()->outstandingAmountCents())->toBe(2_000_000)
        ->and($first->fresh()->paymentAllocations)->toHaveCount(2);
    Livewire::test(SupplierPurchases::class)
        ->call('showInvoice', $first->id)
        ->assertSee('Bank returned payment')
        ->assertSee($posted->reference);
});

test('posted opening supplier invoices are payable and retain allocation and reversal history', function () {
    [$actor, $cash, , $supplier] = payableSettlementBooks();
    $openingInvoice = SupplierOpeningInvoice::create([
        'supplier_id' => $supplier->id, 'supplier_code_snapshot' => $supplier->code,
        'supplier_name_snapshot' => $supplier->name, 'invoice_number' => 'OPEN-42',
        'invoice_number_normalized' => 'OPEN-42', 'recognition_date' => now('Asia/Manila')->subDays(30)->toDateString(),
        'due_date' => now('Asia/Manila')->subDay()->toDateString(), 'amount_cents' => 20_000,
        'description' => 'Opening payable', 'status' => 'posted',
        'opening_journal_id' => AccountingJournal::where('source_type', 'opening')->value('id'),
        'prepared_by' => $actor->id, 'posted_by' => $actor->id, 'approved_by' => $actor->id,
        'posted_at' => now(), 'approved_at' => now(),
    ]);
    $purchaseInvoice = payableInvoice($supplier, $actor, 'INV-OPEN-SETTLED', 10_000);
    $this->actingAs($actor);
    Livewire::test(CashDisbursements::class)->set('supplierId', (string) $supplier->id)->assertSee('OPEN-42');

    $service = app(CashDisbursementService::class);
    $draft = $service->saveDraft(supplierPaymentInput($cash, $supplier, [
        ['invoice_id' => 'opening:'.$openingInvoice->id, 'description' => 'Opening invoice OPEN-42', 'amount' => '80.00'],
        ['invoice_id' => 'purchase:'.$purchaseInvoice->id, 'description' => 'Purchase invoice INV-OPEN-SETTLED', 'amount' => '100.00'],
    ], '180.00'), $actor->id);
    $posted = $service->post($draft->id, $actor->id);

    expect($openingInvoice->fresh()->paidAmountCents())->toBe(8_000)
        ->and($openingInvoice->fresh()->outstandingAmountCents())->toBe(12_000)
        ->and($openingInvoice->fresh()->payableStatus())->toBe('Partially paid')
        ->and($purchaseInvoice->fresh()->outstandingAmountCents())->toBe(0)
        ->and($purchaseInvoice->fresh()->payableStatus())->toBe('Paid')
        ->and($openingInvoice->fresh()->isOverdueOn(now('Asia/Manila')->toDateString()))->toBeTrue();
    Livewire::test(AccountsPayable::class)
        ->assertSee('Partially paid')
        ->assertSee('120.00')
        ->call('showInvoice', $openingInvoice->id)
        ->assertSee($posted->reference);
    Livewire::test(CashDisbursements::class)
        ->call('showDisbursement', $posted->id)
        ->assertSee('OPEN-42')
        ->assertSee('INV-OPEN-SETTLED');

    $reversal = $service->reverse($posted->id, 'Returned opening payment', $actor->id);
    expect($reversal->lines)->toHaveCount(2)
        ->and($openingInvoice->fresh()->paidAmountCents())->toBe(0)
        ->and($openingInvoice->fresh()->outstandingAmountCents())->toBe(20_000)
        ->and($openingInvoice->fresh()->payableStatus())->toBe('Unpaid')
        ->and($purchaseInvoice->fresh()->outstandingAmountCents())->toBe(10_000)
        ->and($purchaseInvoice->fresh()->payableStatus())->toBe('Unpaid');
    Livewire::test(AccountsPayable::class)
        ->call('showInvoice', $openingInvoice->id)
        ->assertSee('Returned opening payment')
        ->assertSee($posted->reference);
});

test('supplier settlements reject unrelated suppliers, over-allocation and competing payments without partial journals', function () {
    [$actor, $cash, , $supplier] = payableSettlementBooks();
    $invoice = payableInvoice($supplier, $actor, 'INV-10', 1_000_000);
    $otherSupplier = Supplier::create(['code' => 'SET-2', 'name' => 'Other Supplier', 'created_by' => $actor->id]);
    $unrelated = payableInvoice($otherSupplier, $actor, 'OTHER-1', 1_000_000);
    $service = app(CashDisbursementService::class);
    $base = supplierPaymentInput($cash, $supplier, [
        ['invoice_id' => 'purchase:'.$invoice->id, 'description' => 'INV-10', 'amount' => '10000.00'],
    ], '10000.00');

    expect(fn () => $service->saveDraft(supplierPaymentInput($cash, $supplier, [
        ['invoice_id' => 'purchase:'.$unrelated->id, 'description' => 'Wrong supplier', 'amount' => '10000.00'],
    ], '10000.00'), $actor->id))->toThrow(ValidationException::class);
    $excess = supplierPaymentInput($cash, $supplier, [
        ['invoice_id' => 'purchase:'.$invoice->id, 'description' => 'Excess', 'amount' => '10000.01'],
    ], '10000.01');
    expect(fn () => $service->saveDraft($excess, $actor->id))->toThrow(ValidationException::class);
    $duplicateInvoiceOverrun = supplierPaymentInput($cash, $supplier, [
        ['invoice_id' => 'purchase:'.$invoice->id, 'description' => 'First part', 'amount' => '6000.00'],
        ['invoice_id' => 'purchase:'.$invoice->id, 'description' => 'Second part', 'amount' => '6000.00'],
    ], '12000.00');
    expect(fn () => $service->saveDraft($duplicateInvoiceOverrun, $actor->id))->toThrow(ValidationException::class);

    $firstDraft = $service->saveDraft($base, $actor->id);
    $competingDraft = $service->saveDraft([...$base, 'reference' => 'COMPETING-PAYMENT'], $actor->id);
    $event = 'eloquent.creating: '.AccountingJournalLine::class;
    Event::listen($event, function (AccountingJournalLine $line): void {
        if ($line->journal?->source_type === 'cash_disbursement') {
            throw new RuntimeException('Simulated AP payment journal failure.');
        }
    });
    try {
        expect(fn () => $service->post($firstDraft->id, $actor->id))
            ->toThrow(RuntimeException::class, 'Simulated AP payment journal failure.');
    } finally {
        Event::forget($event);
    }
    expect($firstDraft->fresh()->status)->toBe('draft')
        ->and($invoice->fresh()->outstandingAmountCents())->toBe(1_000_000)
        ->and(AccountingJournal::where('source_type', 'cash_disbursement')->count())->toBe(0);
    $service->post($firstDraft->id, $actor->id);
    expect(fn () => $service->post($competingDraft->id, $actor->id))->toThrow(ValidationException::class);
    expect($competingDraft->fresh()->status)->toBe('draft')
        ->and($invoice->fresh()->outstandingAmountCents())->toBe(0)
        ->and(AccountingJournal::where('source_type', 'cash_disbursement')->count())->toBe(1)
        ->and(AccountingJournalLine::whereHas('journal', fn ($query) => $query->where('source_type', 'cash_disbursement'))->count())->toBe(2);

});
