<?php

use App\Livewire\Accounting\AccountingPeriods;
use App\Livewire\Accounting\CashDisbursements;
use App\Livewire\Accounting\FinancialStatements;
use App\Livewire\Accounting\GeneralLedger;
use App\Livewire\Accounting\OpeningBooks;
use App\Livewire\Accounting\SupplierPurchases;
use App\Livewire\Accounting\TrialBalance;
use App\Livewire\Pos\PointOfSale;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Illuminate\Support\Carbon;
uses(RefreshDatabase::class);


test('authorized activated books carry received purchases through sale, settlement, corrections, reports, and period close', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00', 'Asia/Manila'));
    $item = Inventory::create([
        'name' => 'Workflow cement', 'category' => 'Cement', 'qty' => '10.00', 'unit' => 'bag',
        'unit_cost' => '50.00', 'selling_price' => '180.00', 'reorder_level' => '0',
    ]);
    $accounts = setupPosAccountingBooks([$item], [$item->id => '500.00']);
    $reviewer = Employee::query()->where('username', 'like', 'pos.books.%')->firstOrFail();
    $ap = AccountingAccount::create([
        'code' => '2100', 'name' => 'Accounts Payable', 'type' => 'Liability',
        'classification' => 'accounts_payable', 'normal_balance' => 'credit', 'is_active' => true,
        'approved_at' => now(), 'approved_by' => $reviewer->id,
    ]);
    AccountingAccount::create([
        'code' => '1300', 'name' => 'Input VAT', 'type' => 'Asset', 'classification' => 'input_vat',
        'normal_balance' => 'debit', 'is_active' => true, 'approved_at' => now(), 'approved_by' => $reviewer->id,
    ]);
    foreach (['accounts_payable' => $ap, 'recovery_offset' => $accounts['capital'], 'adjustment' => $accounts['capital']] as $source => $account) {
        AccountingPostingMapping::create([
            'source' => $source, 'accounting_account_id' => $account->id,
            'approved_at' => now(), 'approved_by' => $reviewer->id,
        ]);
    }
    $actor = grantEmployeeTestPermissions(Employee::create([
        'username' => 'workflow.'.fake()->unique()->numerify('####'),
        'email' => fake()->unique()->safeEmail(), 'password' => Hash::make('workflow-password'),
    ]), [
        'accounting.view', 'accounting.activate-books', 'accounting.prepare-supplier-purchases',
        'accounting.post-supplier-purchases', 'accounting.correct-supplier-purchases',
        'accounting.prepare-disbursements', 'accounting.post-disbursements',
        'accounting.close-period', 'pos.checkout', 'inventory.valuation.approve',
    ]);

    $this->actingAs($actor);
    Livewire::test(OpeningBooks::class)->call('activateProduction')
        ->assertHasNoErrors()->assertSee('Production activated');

    $supplier = Supplier::create(['code' => 'WF-1', 'name' => 'Workflow Supplier', 'created_by' => $actor->id]);
    Livewire::test(SupplierPurchases::class)
        ->set('supplierId', (string) $supplier->id)
        ->set('invoiceNumber', 'WF-INV-1')
        ->set('recognitionDate', now('Asia/Manila')->toDateString())
        ->set('dueDate', now('Asia/Manila')->addDays(20)->toDateString())
        ->set('description', 'Received workflow stock')
        ->set('receiptConfirmed', true)
        ->set('lines', [[
            'inventory_id' => (string) $item->id, 'accounting_account_id' => '',
            'description' => 'Cement received', 'quantity' => '2.00', 'amount' => '120.00',
        ]])
        ->call('saveDraft')->assertHasNoErrors();
    $invoice = App\Models\SupplierPurchaseInvoice::query()->where('invoice_number', 'WF-INV-1')->sole();
    Livewire::test(SupplierPurchases::class)->call('postInvoice', $invoice->id)
        ->assertHasNoErrors()->assertSee('Supplier purchase, payable, valued receipts, and journal posted');
    expect($invoice->fresh()->status)->toBe('posted')
        ->and($invoice->fresh()->accounting_journal_id)->not->toBeNull()
        ->and($item->fresh()->qty)->toBe('12.00');

    Livewire::test(PointOfSale::class)->call('addToCart', $item->id)
        ->set('amountReceived', '201.60')->call('checkout')->assertHasNoErrors();
    $sale = App\Models\PosTransaction::query()->where('status', 'completed')->sole();
    expect(AccountingJournal::query()->where('source_type', 'pos_sale')->where('source_id', (string) $sale->id)->exists())->toBeTrue();


    Livewire::test(CashDisbursements::class)
        ->set('supplierId', (string) $supplier->id)
        ->set('paymentDate', now('Asia/Manila')->toDateString())
        ->set('method', 'Bank Transfer')
        ->set('moneyAccountId', (string) $accounts['bank']->id)
        ->set('reference', 'WF-PAY-1')
        ->set('description', 'Partial supplier settlement')
        ->set('evidenceReference', 'WF-ADVICE-1')
        ->set('amount', '60.00')
        ->set('receiptConfirmed', true)
        ->set('allocations', [[
            'invoice_id' => 'purchase:'.$invoice->id,
            'description' => 'Partial settlement of WF-INV-1', 'amount' => '60.00',
        ]])
        ->call('saveDraft')->assertHasNoErrors();
    $payment = App\Models\CashDisbursement::query()->where('reference', 'WF-PAY-1')->sole();
    Livewire::test(CashDisbursements::class)->call('postDisbursement', $payment->id)
        ->assertHasNoErrors()->assertSee('Direct payment and balanced cash/bank journal posted atomically');
    expect($invoice->fresh()->outstandingAmountCents())->toBe(6000)
        ->and($payment->fresh()->status)->toBe('posted');

    Livewire::test(SupplierPurchases::class)->call('startCorrection', $invoice->id)
        ->set('correctionReason', 'Reviewed supplier price correction')
        ->set('correctionLines.0.corrected_amount', '100.00')
        ->set('correctionLines.0.remaining_inventory', '10.00')
        ->set('correctionLines.0.consumed_cost', '10.00')
        ->set('correctionLines.0.consumed_accounting_account_id', (string) $accounts['cogs']->id)
        ->call('postCorrection', $invoice->id)->assertHasNoErrors();
    $activeInvoice = App\Models\SupplierPurchaseInvoice::query()->where('correction_of_id', $invoice->id)->firstOrFail();
    Livewire::test(SupplierPurchases::class)->call('startVatReclassification', $activeInvoice->id)
        ->set('vatReclassificationReason', 'Reviewed supplier VAT invoice')
        ->set('vatReclassificationAmount', '10.00')
        ->set('vatReclassificationLines.0.amount', '10.00')
        ->set('vatReclassificationLines.0.remaining_inventory', '5.00')
        ->set('vatReclassificationLines.0.consumed_cost', '5.00')
        ->set('vatReclassificationLines.0.consumed_accounting_account_id', (string) $accounts['cogs']->id)
        ->call('postVatReclassification', $activeInvoice->id)->assertHasNoErrors();
    expect(DB::table('supplier_purchase_corrections')->where('supplier_purchase_invoice_id', $invoice->id)->exists())->toBeTrue()
        ->and(DB::table('supplier_purchase_vat_reclassifications')->where('supplier_purchase_invoice_id', $activeInvoice->id)->sum('amount_cents'))->toBe(1000);

    Livewire::test(GeneralLedger::class)->set('accountId', (string) $accounts['bank']->id)
        ->assertSee('Partial supplier settlement');
    Livewire::test(TrialBalance::class)->assertSee('Books balance:');
    Livewire::test(FinancialStatements::class)->assertSee('Sales')->assertSee('Gross Profit');
    Carbon::setTestNow(Carbon::parse('2026-02-01 12:00:00', 'Asia/Manila'));
    $period = App\Models\AccountingPostingPeriod::query()->whereDate('starts_on', '2026-01-01')->sole();
    Livewire::test(AccountingPeriods::class)->set('year', $period->fiscal_year)
        ->set('reason', 'Workflow schedules reconciled')->call('close', $period->id)
        ->assertHasNoErrors()->assertSee('Accounting month closed');
    expect($period->fresh()->status)->toBe('closed')
        ->and($period->fresh()->closed_by)->toBe($actor->id);
    // Reports render the live posted sources before the eligible month is closed.
    Livewire::test(GeneralLedger::class)->assertSee('General Ledger');
    Livewire::test(TrialBalance::class)->assertSee('Trial Balance');
    Livewire::test(FinancialStatements::class)->assertSee('Financial Statements');
});
