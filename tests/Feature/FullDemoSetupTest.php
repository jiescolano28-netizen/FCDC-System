<?php

use App\Models\AccountingJournal;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\PosTransaction;
use App\Models\SupplierOpeningInvoice;
use App\Models\SupplierPurchaseInvoice;
use App\Services\Accounting\OpeningBooksService;
use App\Support\DemoDatabaseIsolation;
use Database\Seeders\FullDemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config([
        'database.default' => 'demo',
        'database.connections.demo.driver' => 'sqlite', 'database.connections.demo.database' => ':memory:',
        'demo.enabled' => true, 'demo.allowed_environments' => ['testing'], 'demo.allow_in_memory_test' => true,
        'activitylog.database_connection' => 'demo', 'queue.failed.database' => 'demo', 'queue.batching.database' => 'demo',
    ]);
    DB::purge('demo');
    Artisan::call('migrate', ['--force' => true]);
});

test('full demo workflows reconcile stock payables and posted books and preserve work on rerun', function (int $month, int $day) {
    $this->travelTo(now('Asia/Manila')->setDate(2026, $month, $day)->setTime(12, 0));
    Artisan::call('db:seed', ['--class' => FullDemoSeeder::class, '--force' => true]);
    expect(Artisan::output())->toContain('Initial password for these demo users:');
    expect(app(OpeningBooksService::class)->readiness()['production_activated'])->toBeTrue();

    foreach (AccountingJournal::where('status', 'posted')->with('lines')->get() as $journal) {
        expect($journal->lines->sum('debit_cents'))->toBe($journal->lines->sum('credit_cents'));
    }
    $stockLedger = DB::table('accounting_journal_lines')->join('accounting_journals', 'accounting_journals.id', '=', 'accounting_journal_lines.accounting_journal_id')
        ->join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_journal_lines.accounting_account_id')
        ->where('accounting_journals.status', 'posted')->where('classification', 'inventory')
        ->selectRaw('SUM(debit_cents - credit_cents) AS balance')->value('balance');
    expect((int) Inventory::sum('carrying_value_cents'))->toBe((int) $stockLedger);
    expect(Inventory::where('name', 'DEMO Recovered Steel Bars')->firstOrFail()->qty)->toBe('118.00');
    expect(Inventory::where('name', 'DEMO Recovered Timber Boards')->firstOrFail()->qty)->toBe('64.00');

    $invoice = SupplierPurchaseInvoice::where('invoice_number', 'DEMO-PUR-001')->firstOrFail();
    expect($invoice->outstandingAmountCents())->toBe(200000);
    $apLedger = DB::table('accounting_journal_lines')->join('accounting_journals', 'accounting_journals.id', '=', 'accounting_journal_lines.accounting_journal_id')
        ->join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_journal_lines.accounting_account_id')
        ->where('accounting_journals.status', 'posted')->where('classification', 'accounts_payable')
        ->selectRaw('SUM(credit_cents - debit_cents) AS balance')->value('balance');
    expect((int) $apLedger)->toBe(SupplierOpeningInvoice::activePosted()->get()->sum(fn ($row) => $row->outstandingAmountCents()) + $invoice->outstandingAmountCents());
    foreach (PosTransaction::with('vatRecord', 'lines')->get() as $sale) {
        expect($sale->vatRecord->output_vat)->toBe($sale->vat_amount);
        expect((int) round((float) $sale->total * 100))->toBe((int) round(((float) $sale->subtotal + (float) $sale->vat_amount) * 100));
    }
    $cashier = Employee::where('username', 'demo.cashier')->firstOrFail();
    expect($cashier->can('pos.checkout'))->toBeTrue()->and($cashier->can('accounting.activate-books'))->toBeFalse();
    $draft = AccountingJournal::where('reference', 'DEMO-JV-DRAFT')->firstOrFail();
    $draft->update(['description' => 'User changed this draft after setup']);
    $password = Hash::make('Changed-password!123');
    $cashier->update(['password' => $password]);
    $before = AccountingJournal::with('lines')->get()->toArray();
    Artisan::call('demo:setup');
    expect(Artisan::output())->toContain('already exists');
    expect(AccountingJournal::with('lines')->get()->toArray())->toBe($before);
    expect($cashier->fresh()->password)->toBe($password);
})->with(['midyear cutover' => [10, 16], 'January 1 cutover without YTD' => [1, 1]]);

test('demo setup refuses existing business data without replacing it', function () {
    $employee = Employee::create(['username' => 'real-operator', 'email' => 'operator@example.test', 'password' => Hash::make('Unchanged-password!123')]);
    $before = $employee->fresh()->getAttributes();
    expect(fn () => app(FullDemoSeeder::class)->run(app(DemoDatabaseIsolation::class)))->toThrow(RuntimeException::class, 'empty business database');
    expect($employee->fresh()->getAttributes())->toBe($before);
    expect(AccountingJournal::count())->toBe(0);
});

test('demo setup refuses production even if demo flags are enabled', function () {
    config(['app.env' => 'production', 'demo.allowed_environments' => ['production']]);
    expect(fn () => app(FullDemoSeeder::class)->run(app(DemoDatabaseIsolation::class)))->toThrow(RuntimeException::class, 'not explicitly allowlisted');
    expect(Employee::count())->toBe(0);
});

test('a failed full setup rolls back employees and accounting work', function () {
    DB::table('accounting_accounts')->insert([
        'code' => '1010', 'name' => 'Conflicting existing cash', 'type' => 'Asset', 'classification' => 'cash', 'normal_balance' => 'debit',
        'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    expect(fn () => app(FullDemoSeeder::class)->run(app(DemoDatabaseIsolation::class)))->toThrow(RuntimeException::class, 'conflicts');
    expect(Employee::count())->toBe(0);
    expect(DB::table('accounting_accounts')->pluck('name')->all())->toBe(['Conflicting existing cash']);
    expect(DB::table('roles')->count())->toBe(0);
});
