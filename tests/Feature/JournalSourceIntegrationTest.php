<?php

use App\Livewire\Accounting\GeneralLedger;
use App\Livewire\Accounting\JournalEntry;
use App\Livewire\Accounting\TrialBalance;
use App\Livewire\Inventory\OperatingCostRecordManagement;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Models\OperatingCostRecord;
use App\Models\ReworkingCostRecord;
use App\Services\Accounting\JournalSourcePostingService;
use App\Services\Accounting\OpeningBooksService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class)->beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $readiness = Mockery::mock(OpeningBooksService::class);
    $readiness->shouldReceive('readiness')->andReturn([
        'production_activated' => true,
        'production' => 'Production activated',
    ]);
    app()->instance(OpeningBooksService::class, $readiness);
});

function journalSourceEmployee(array $permissions = [
    'accounting.view',
    'accounting.create-journal-entry',
    'accounting.approve-journal-entry',
    'accounting.post-journal-entry',
    'inventory.view',
]): Employee
{
    static $counter = 0;
    $counter++;

    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'journal.source.'.$counter,
        'email' => 'journal.source'.$counter.'@example.com',
        'password' => Hash::make('journal-source-password'),
    ]), $permissions);
}

function journalSourceAccount(string $code, string $name, string $type, string $classification, string $normalBalance, bool $approved = true): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => $name,
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $normalBalance,
        'is_active' => true,
        'approved_at' => $approved ? now() : null,
    ]);
}

function prepareJournalSourceBooks(): array
{
    $accounts = [
        'cash' => journalSourceAccount('1010', 'Cash on Hand', 'Asset', 'cash', 'debit'),
        'bank' => journalSourceAccount('1020', 'Cash in Bank', 'Asset', 'bank', 'debit'),
        'input_vat' => journalSourceAccount('1030', 'Input VAT', 'Asset', 'input_vat', 'debit'),
        'inventory' => journalSourceAccount('1040', 'Recovered Materials Inventory', 'Asset', 'inventory', 'debit'),
        'ap' => journalSourceAccount('2010', 'Accounts Payable', 'Liability', 'accounts_payable', 'credit'),
        'capital' => journalSourceAccount('3010', 'Share Capital', 'Equity', 'capital', 'credit'),
        'reworking' => journalSourceAccount('6010', 'Reworking Costs', 'Expense', 'operating_expense', 'debit'),
        'rent' => journalSourceAccount('6120', 'Rent Expense', 'Expense', 'operating_expense', 'debit'),
    ];

    $today = now('Asia/Manila')->toDateString();
    $period = AccountingPostingPeriod::firstOrCreateForDate($today);
    $employee = journalSourceEmployee(['accounting.view']);

    $opening = AccountingJournal::create([
        'book_key' => 'FCDC',
        'reference' => 'OPENING-SOURCE-TEST',
        'source_type' => 'opening',
        'source_id' => 'FCDC',
        'accounting_date' => $today,
        'posting_period_id' => $period->id,
        'description' => 'Approved cutover for source integration',
        'status' => 'draft',
        'prepared_by' => $employee->id,
    ]);
    $opening->lines()->createMany([
        ['accounting_account_id' => $accounts['cash']->id, 'debit_cents' => 500000, 'credit_cents' => 0],
        ['accounting_account_id' => $accounts['capital']->id, 'debit_cents' => 0, 'credit_cents' => 500000],
    ]);
    $opening->forceFill(['status' => 'posted', 'posted_at' => now('UTC'), 'posted_by' => $employee->id])->save();

    return [$accounts, $period];
}

function createJournalSourceReworking(array $overrides = []): ReworkingCostRecord
{
    return ReworkingCostRecord::create(array_merge([
        'employee_id' => journalSourceEmployee(['inventory.view'])->id,
        'source_record_id' => (int) ReworkingCostRecord::query()->max('source_record_id') + 1,
        'project_name' => 'Warehouse Dismantling',
        'batch_number' => 'BATCH-GL-001',
        'record_date' => now('Asia/Manila')->toDateString(),
        'description' => 'Cleaning and repair of recovered steel bars',
        'total_reworking_cost' => '1000.00',
        'input_vat' => '120.00',
        'payment_method' => 'Cash',
    ], $overrides));
}

function createJournalSourceOperating(array $overrides = []): OperatingCostRecord
{
    return OperatingCostRecord::create(array_merge([
        'employee_id' => journalSourceEmployee(['inventory.view'])->id,
        'expense_date' => now('Asia/Manila')->toDateString(),
        'reference_number' => 'OC-GL-001',
        'expense_category' => 'Rent Expense',
        'description' => 'Warehouse rent for the current month',
        'amount' => '500.00',
        'payment_method' => 'Bank Transfer',
    ], $overrides));
}

test('a reworking cost source posts net cost, recorded input VAT and the cash credit exactly once', function () {
    [$accounts] = prepareJournalSourceBooks();
    $record = createJournalSourceReworking();
    $employee = journalSourceEmployee(['accounting.post-journal-entry']);

    $journal = app(JournalSourcePostingService::class)->postReworkingCost($record->id, $employee->id);
    $again = app(JournalSourcePostingService::class)->postReworkingCost($record->id, $employee->id);

    $lines = $journal->lines->keyBy('accounting_account_id');

    expect($journal->status)->toBe('posted')
        ->and($journal->source_type)->toBe('reworking_cost')
        ->and($journal->source_id)->toBe((string) $record->id)
        ->and($journal->manual_input)->toBeFalse()
        ->and($lines[$accounts['reworking']->id]->debit_cents)->toBe(100000)
        ->and($lines[$accounts['input_vat']->id]->debit_cents)->toBe(12000)
        ->and($lines[$accounts['cash']->id]->credit_cents)->toBe(112000)
        ->and($journal->lines->sum('debit_cents'))->toBe($journal->lines->sum('credit_cents'))
        ->and($again->id)->toBe($journal->id)
        ->and(AccountingJournal::query()->where('source_type', 'reworking_cost')->count())->toBe(1);
});

test('operating cost categories post to their official expense accounts and use the recorded bank settlement', function () {
    [$accounts] = prepareJournalSourceBooks();
    $record = createJournalSourceOperating();
    $employee = journalSourceEmployee(['accounting.post-journal-entry']);

    $journal = app(JournalSourcePostingService::class)->postOperatingCost($record->id, $employee->id);
    $lines = $journal->lines->keyBy('accounting_account_id');

    expect($journal->status)->toBe('posted')
        ->and($journal->source_type)->toBe('operating_cost')
        ->and($journal->external_reference)->toBe('OC-GL-001')
        ->and($lines[$accounts['rent']->id]->debit_cents)->toBe(50000)
        ->and($lines[$accounts['bank']->id]->credit_cents)->toBe(50000)
        ->and($journal->lines->sum('debit_cents'))->toBe($journal->lines->sum('credit_cents'));
});

test('source records without a supported settlement method are ineligible and never invent a credit account', function () {
    prepareJournalSourceBooks();
    $missing = createJournalSourceReworking(['payment_method' => null]);
    $unsupported = createJournalSourceOperating(['payment_method' => 'GCash']);
    $employee = journalSourceEmployee(['accounting.post-journal-entry']);

    $candidates = app(JournalSourcePostingService::class)->pendingCandidates();

    expect($candidates['reworking_cost']['records'][0]['eligible'])->toBeFalse()
        ->and($candidates['operating_cost']['records'][0]['eligible'])->toBeFalse()
        ->and(fn () => app(JournalSourcePostingService::class)->postReworkingCost($missing->id, $employee->id))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(JournalSourcePostingService::class)->postOperatingCost($unsupported->id, $employee->id))
        ->toThrow(ValidationException::class)
        ->and(AccountingJournal::query()->whereIn('source_type', ['reworking_cost', 'operating_cost'])->count())->toBe(0);
});

test('the journal workspace generates source journals and filters by source, status and account type', function () {
    [$accounts] = prepareJournalSourceBooks();
    $record = createJournalSourceReworking();
    $employee = journalSourceEmployee();
    $this->actingAs($employee);

    Livewire::test(JournalEntry::class)
        ->assertSee('Source-Generated Journals')
        ->assertSee('Reworking cost records')
        ->assertSee('BATCH-GL-001')
        ->call('generateSourceJournal', 'reworking_cost', $record->id)
        ->assertHasNoErrors()
        ->assertSee('Source journal RC-'.$record->id)
        ->set('sourceFilter', 'reworking_cost')
        ->set('statusFilter', 'posted')
        ->set('accountTypeFilter', 'operating_expense')
        ->assertSee('BATCH-GL-001')
        ->assertSee('RC-'.$record->id)
        ->assertSee('Reworking cost');

    Livewire::test(JournalEntry::class)
        ->call('generateSourceJournal', 'reworking_cost', $record->id)
        ->assertHasNoErrors();

    expect(AccountingJournal::query()->where('source_type', 'reworking_cost')->count())->toBe(1)
        ->and(AccountingJournal::query()->where('source_type', 'reworking_cost')->sole()->lines->count())->toBe(3)
        ->and($accounts['reworking']->fresh()->used_at)->not->toBeNull();
});

test('accounts payable and inventory manual entries require the reviewed manual-input workflow and an external reference', function () {
    [$accounts] = prepareJournalSourceBooks();
    $preparer = journalSourceEmployee();
    $reviewer = journalSourceEmployee(['accounting.view', 'accounting.approve-journal-entry', 'accounting.post-journal-entry']);
    $this->actingAs($preparer);

    $withoutReference = [
        'accountingDate' => now('Asia/Manila')->toDateString(),
        'reference' => 'MANUAL-AP-NO-REF',
        'externalReference' => '',
        'description' => 'Unsupported payable entry',
        'lines' => [
            ['accountId' => (string) $accounts['inventory']->id, 'debit' => '100.00', 'credit' => ''],
            ['accountId' => (string) $accounts['ap']->id, 'debit' => '', 'credit' => '100.00'],
        ],
    ];
    fillJournalSourceForm(Livewire::test(JournalEntry::class), $withoutReference)->call('saveDraft')->assertHasNoErrors();
    $blocked = AccountingJournal::where('reference', 'MANUAL-AP-NO-REF')->sole();
    Livewire::test(JournalEntry::class)->call('submitForReview', $blocked->id)->assertHasErrors('journal');
    expect($blocked->fresh()->status)->toBe('draft');

    fillJournalSourceForm(Livewire::test(JournalEntry::class), array_merge($withoutReference, [
        'reference' => 'MANUAL-AP-WITH-REF',
        'externalReference' => 'SUPPLIER-INVOICE-100',
    ]))->call('saveDraft')->assertHasNoErrors();
    $supported = AccountingJournal::where('reference', 'MANUAL-AP-WITH-REF')->sole();

    expect($supported->manual_input)->toBeTrue();

    Livewire::actingAs($preparer)->test(JournalEntry::class)->call('submitForReview', $supported->id)->assertHasNoErrors();
    Livewire::actingAs($reviewer)->test(JournalEntry::class)->call('approveDraft', $supported->id)->assertHasNoErrors();
    Livewire::actingAs($reviewer)->test(JournalEntry::class)->call('postDraft', $supported->id)->assertHasNoErrors();

    expect($supported->fresh()->status)->toBe('posted')
        ->and($supported->fresh()->manual_input)->toBeTrue()
        ->and($supported->fresh()->external_reference)->toBe('SUPPLIER-INVOICE-100');
});

test('source-generated journals update the general ledger and trial balance without duplicate postings', function () {
    [$accounts] = prepareJournalSourceBooks();
    $reworking = createJournalSourceReworking();
    $operating = createJournalSourceOperating();
    $employee = journalSourceEmployee();
    $this->actingAs($employee);

    Livewire::test(JournalEntry::class)->call('generateSourceJournal', 'reworking_cost', $reworking->id)->assertHasNoErrors();
    Livewire::test(JournalEntry::class)->call('generateSourceJournal', 'operating_cost', $operating->id)->assertHasNoErrors();
    Livewire::test(JournalEntry::class)->call('generateSourceJournal', 'operating_cost', $operating->id)->assertHasNoErrors();

    expect(AccountingJournal::query()->where('source_type', 'operating_cost')->count())->toBe(1);

    Livewire::actingAs($employee)->test(GeneralLedger::class)
        ->set('accountId', (string) $accounts['reworking']->id)
        ->assertSee('RC-'.$reworking->id)
        ->assertSee('1,000.00')
        ->assertSee(route('inventory.reworking-cost-records'), false);

    Livewire::actingAs($employee)->test(TrialBalance::class)
        ->set('fromDate', now('Asia/Manila')->toDateString())
        ->set('toDate', now('Asia/Manila')->toDateString())
        ->assertSee('Reworking Costs')
        ->assertSee('Input VAT')
        ->assertSee('Rent Expense')
        ->assertSee('Books balance');
});

test('a validated operating cost record posts its balanced journal automatically on save', function () {
    [$accounts] = prepareJournalSourceBooks();
    $employee = journalSourceEmployee(['accounting.view', 'inventory.view', 'inventory.create', 'inventory.update']);
    $this->actingAs($employee);

    Livewire::test(OperatingCostRecordManagement::class)
        ->set('expenseDate', now('Asia/Manila')->toDateString())
        ->set('referenceNumber', 'OC-AUTO-001')
        ->set('expenseCategory', 'Rent Expense')
        ->set('expenseDescription', 'Automatically posted warehouse rent')
        ->set('amount', '500.00')
        ->set('paymentMethod', 'Bank Transfer')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('OC-AUTO-001')
        ->assertSee('Posted');

    $record = OperatingCostRecord::query()->where('reference_number', 'OC-AUTO-001')->sole();
    $journal = AccountingJournal::query()
        ->where('source_type', 'operating_cost')
        ->where('source_id', (string) $record->id)
        ->with('lines')
        ->sole();
    $lines = $journal->lines->keyBy('accounting_account_id');

    expect($journal->status)->toBe('posted')
        ->and($journal->posted_by)->toBe($employee->id)
        ->and($journal->approved_by)->toBeNull()
        ->and($lines[$accounts['rent']->id]->debit_cents)->toBe(50000)
        ->and($lines[$accounts['bank']->id]->credit_cents)->toBe(50000)
        ->and($journal->lines->sum('debit_cents'))->toBe($journal->lines->sum('credit_cents'));

    Livewire::actingAs($employee)->test(GeneralLedger::class)
        ->set('accountId', (string) $accounts['rent']->id)
        ->assertSee('OPEX-'.$record->id)
        ->assertSee('500.00');
});

test('operating cost records remain available without a journal until approved accounts are configured', function () {
    $employee = journalSourceEmployee(['inventory.view', 'inventory.create']);
    $this->actingAs($employee);

    Livewire::test(OperatingCostRecordManagement::class)
        ->set('expenseDate', now('Asia/Manila')->toDateString())
        ->set('referenceNumber', 'OC-AUTO-002')
        ->set('expenseCategory', 'Rent Expense')
        ->set('expenseDescription', 'Saved before accounting configuration')
        ->set('amount', '250.00')
        ->set('paymentMethod', 'Cash')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Not posted');

    expect(OperatingCostRecord::query()->where('reference_number', 'OC-AUTO-002')->exists())->toBeTrue()
        ->and(AccountingJournal::query()->where('source_type', 'operating_cost')->count())->toBe(0);
});

test('posted operating cost records cannot be edited or deleted as source records', function () {
    prepareJournalSourceBooks();
    $employee = journalSourceEmployee([
        'accounting.view',
        'inventory.view',
        'inventory.create',
        'inventory.update',
        'inventory.delete',
    ]);
    $this->actingAs($employee);

    Livewire::test(OperatingCostRecordManagement::class)
        ->set('expenseDate', now('Asia/Manila')->toDateString())
        ->set('referenceNumber', 'OC-AUTO-003')
        ->set('expenseCategory', 'Rent Expense')
        ->set('expenseDescription', 'Posted rent')
        ->set('amount', '500.00')
        ->set('paymentMethod', 'Bank Transfer')
        ->call('save')
        ->assertHasNoErrors();

    $record = OperatingCostRecord::query()->where('reference_number', 'OC-AUTO-003')->sole();

    Livewire::test(OperatingCostRecordManagement::class)
        ->call('edit', $record->id)
        ->set('amount', '600.00')
        ->call('save')
        ->assertHasErrors('referenceNumber');

    Livewire::test(OperatingCostRecordManagement::class)
        ->call('delete', $record->id)
        ->assertHasErrors('delete');

    expect($record->fresh()->amount)->toBe('500.00')
        ->and(OperatingCostRecord::query()->whereKey($record->id)->exists())->toBeTrue()
        ->and(AccountingJournal::query()->where('source_type', 'operating_cost')->count())->toBe(1);
});

function fillJournalSourceForm($component, array $data)
{
    foreach ($data as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}
