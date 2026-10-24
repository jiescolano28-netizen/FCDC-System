<?php

namespace Database\Seeders;

use App\Livewire\Pos\PointOfSale;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\DemolitionProject;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\InventoryBatchCategory;
use App\Services\Accounting\CashDisbursementService;
use App\Services\Accounting\ManualJournalService;
use App\Services\Accounting\OpeningBooksService;
use App\Services\Accounting\SupplierPayablesService;
use App\Services\Accounting\SupplierPurchaseService;
use App\Services\Inventory\AssessRecoveredMaterial;
use App\Support\DemoDatabaseIsolation;
use App\Support\RolePermissionCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class FullDemoSeeder extends Seeder
{
    public function run(DemoDatabaseIsolation $isolation): void
    {
        $isolation->verify();
        $previousUser = Auth::user();
        $password = Str::password(20);

        try {
            $created = DB::transaction(function () use ($isolation, $password): bool {
                // This final posted journal is immutable and committed with the entire dataset.
                if (AccountingJournal::where('reference', 'DEMO-SETUP-COMPLETE')->where('status', 'posted')->exists()) {
                    return false;
                }
                foreach (['employees', 'accounting_accounts', 'inventories', 'inventory_batch_categories', 'suppliers', 'demolition_projects',
                    'accounting_journals', 'accounting_posting_mappings', 'accounting_posting_periods',
                    'accounting_ytd_summaries', 'opening_inventory_valuations', 'pos_transactions',
                    'cash_disbursements', 'supplier_purchase_invoices'] as $table) {
                    if (DB::table($table)->exists()) {
                        throw new RuntimeException("Full demo setup requires an empty business database; {$table} contains existing data. Nothing was replaced.");
                    }
                }

                $this->call(RolePermissionSeeder::class);
                $employees = $this->employees($password);
                $preparer = $employees['demo.accountant'];
                $reviewer = $employees['demo.admin'];
                Auth::setUser($preparer);
                $accounts = $this->accounts($isolation, $reviewer->id);
                $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
                $items = $this->inventory($preparer->id);
                $suppliers = $this->suppliers($preparer->id);
                $this->opening($today, $items, $accounts, $suppliers, $preparer->id, $reviewer->id);

                // The same readiness-gated activation is simulated only in this isolated demo.
                // Reports otherwise intentionally remain unavailable. This is not real cutover evidence.
                app(OpeningBooksService::class)->activateProduction($reviewer->id);

                $this->purchasesAndPayments($today->toDateString(), $items, $accounts, $suppliers, $preparer->id, $reviewer->id);
                $this->demolition($today->toDateString(), $items, $reviewer->id);
                $this->sales($items, $employees['demo.cashier']);
                Auth::setUser($preparer);
                $this->journals($today->toDateString(), $accounts, $preparer->id, $reviewer->id);

                return true;
            });
        } finally {
            if ($previousUser) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetUser();
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        if (! $created) {
            $this->command?->info('Full demo data already exists. Existing records and passwords were preserved.');

            return;
        }
        $this->command?->warn('SYNTHETIC DEMO ONLY. Never use these balances, evidence, or approvals for a real business.');
        $this->command?->info('Login emails: demo.admin@example.test, demo.accountant@example.test, demo.cashier@example.test, demo.warehouse@example.test, demo.viewer@example.test');
        $this->command?->line('Initial password for these demo users: '.$password);
        $this->command?->info('Save the password now; rerunning setup will not reset it.');
    }

    private function employees(string $password): array
    {
        $roles = [
            RolePermissionCatalog::ADMIN_ROLE => RolePermissionCatalog::names(),
            'Demo Accountant' => array_values(array_filter(RolePermissionCatalog::names(), fn ($name) => str_starts_with($name, 'accounting.') || in_array($name, ['dashboard.view', 'reports.view', 'tax.view', 'inventory.view']))),
            'Demo Cashier' => ['dashboard.view', 'pos.view', 'pos.checkout', 'inventory.view'],
            'Demo Warehouse' => ['dashboard.view', 'inventory.view', 'inventory.create', 'inventory.update', 'inventory.movements.record', 'inventory.valuation.approve', 'demolition-projects.view', 'demolition-projects.manage'],
            'Demo Viewer' => ['dashboard.view', 'reports.view', 'accounting.view', 'inventory.view', 'demolition-projects.view', 'tax.view'],
        ];
        foreach ($roles as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }
        $employees = [];
        foreach (['demo.admin' => RolePermissionCatalog::ADMIN_ROLE, 'demo.accountant' => 'Demo Accountant',
            'demo.cashier' => 'Demo Cashier', 'demo.warehouse' => 'Demo Warehouse', 'demo.viewer' => 'Demo Viewer'] as $username => $role) {
            $employee = Employee::create(['username' => $username, 'email' => $username.'@example.test', 'password' => Hash::make($password)]);
            $employee->assignRole($role);
            $employees[$username] = $employee;
        }

        return $employees;
    }

    private function accounts(DemoDatabaseIsolation $isolation, int $reviewerId): array
    {
        $result = app(ProposedChartOfAccountsSeeder::class)->seed($isolation);
        if ($result['conflicts'] !== []) {
            throw new RuntimeException('The official chart conflicts with existing definitions. No demo data was committed.');
        }
        $codes = ['1010', '1020', '1040', '1050', '2010', '2020', '3010', '3020', '4010', '5010', '6010', '6130', '6170', '6180'];
        foreach ($codes as $code) {
            $account = AccountingAccount::where('code', $code)->firstOrFail();
            $account->update(['is_active' => true]);
            $account->update(['approved_at' => now(), 'approved_by' => $reviewerId]);
        }
        $accounts = AccountingAccount::whereIn('code', $codes)->get()->keyBy('code')->all();
        foreach (['cash' => '1010', 'bank' => '1020', 'card_clearing' => '1020', 'sales' => '4010',
            'output_vat' => '2020', 'cogs' => '5010', 'inventory' => '1040', 'accounts_payable' => '2010',
            'recovery_offset' => '6010', 'adjustment' => '6180'] as $source => $code) {
            AccountingPostingMapping::updateOrCreate(['source' => $source], [
                'accounting_account_id' => $accounts[$code]->id,
                'approved_at' => now(),
                'approved_by' => $reviewerId,
            ]);
        }

        return $accounts;
    }

    private function inventory(int $actorId): array
    {
        $items = [];
        foreach ([
            ['steel', 'DEMO Recovered Steel Bars', 'Steel', 100, 150, 250, 20],
            ['lumber', 'DEMO Recovered Timber Boards', 'Lumber', 60, 200, 350, 15],
            ['doors', 'DEMO Solid Wood Doors', 'Fixtures', 10, 1500, 2500, 3],
            ['tiles', 'DEMO Ceramic Tiles', 'Finishes', 200, 50, 90, 50],
            ['wire', 'DEMO Copper Wire Rolls', 'Electrical', 20, 600, 900, 5],
            ['scaffold', 'DEMO Scaffold Frames', 'Equipment', 0, 0, 3500, 5],
        ] as [$key, $name, $category, $quantity, $cost, $price, $reorder]) {
            $batch = InventoryBatchCategory::firstOrCreate(['name' => 'DEMO '.$category], [
                'description' => 'Synthetic demonstration category', 'created_by' => $actorId, 'updated_by' => $actorId,
            ]);
            $items[$key] = Inventory::create([
                'name' => $name, 'category' => $category, 'inventory_batch_category_id' => $batch->id,
                'qty' => $quantity, 'unit' => 'piece', 'unit_cost' => $cost, 'selling_price' => $price,
                'reorder_level' => $reorder, 'status' => 'active', 'description' => 'SYNTHETIC DEMO inventory, not real stock.',
            ]);
        }

        return $items;
    }

    private function suppliers(int $actorId): array
    {
        $suppliers = [];
        foreach (['DEMO-SUP-001' => 'DEMO Rizal Materials Supply', 'DEMO-SUP-002' => 'DEMO Metro Salvage Trading',
            'DEMO-SUP-003' => 'DEMO Site Services'] as $code => $name) {
            $suppliers[] = app(SupplierPayablesService::class)->saveSupplier([
                'code' => $code, 'name' => $name, 'legal_name' => $name, 'tax_identifier' => '',
                'address' => 'Synthetic demo address, San Mateo, Rizal', 'contact_name' => 'Demo Contact',
                'email' => strtolower($code).'@example.test', 'phone' => '000-000-0000',
            ], $actorId);
        }

        return $suppliers;
    }

    private function opening(CarbonImmutable $today, array $items, array $accounts, array $suppliers, int $preparerId, int $reviewerId): void
    {
        $books = app(OpeningBooksService::class);
        $date = $today->toDateString();
        $lines = [];
        foreach ($items as $item) {
            $lines[] = ['inventoryId' => (string) $item->id, 'quantity' => (string) $item->qty,
                'value' => number_format((float) $item->qty * (float) $item->unit_cost, 2, '.', '')];
        }
        $books->saveInventoryValuation($date, 'DEMO ONLY: synthetic opening stock count and valuation', $lines, $preparerId);
        $books->approveInventoryValuation($reviewerId);
        foreach ([18000, 12000] as $index => $amount) {
            app(SupplierPayablesService::class)->saveOpeningInvoice([
                'supplier_id' => $suppliers[$index]->id, 'invoice_number' => 'DEMO-OPEN-'.($index + 1),
                'recognition_date' => $today->subDays(30)->toDateString(), 'due_date' => $today->subDays(5)->toDateString(),
                'amount' => number_format($amount, 2, '.', ''), 'description' => 'Synthetic opening payable', 'terms' => 'Demo Net 25',
            ], $preparerId);
        }
        $midyear = $date !== $today->startOfYear()->toDateString();
        $books->saveOpening($date, [
            ['accountId' => $accounts['1010']->id, 'debit' => '250000.00', 'credit' => ''],
            ['accountId' => $accounts['1020']->id, 'debit' => '500000.00', 'credit' => ''],
            ['accountId' => $accounts['1040']->id, 'debit' => '64000.00', 'credit' => ''],
            ['accountId' => $accounts['2010']->id, 'debit' => '', 'credit' => '30000.00'],
            ['accountId' => $accounts['3010']->id, 'debit' => '', 'credit' => $midyear ? '764000.00' : '784000.00'],
            // The 20,000.00 midyear profit is already embedded in the opening assets.
            // It must not also sit in 3020 Retained Earnings because the balance sheet
            // adds the approved pre-cutover YTD result separately. An opening-source
            // income line is the established balancing plug and is excluded from the
            // income statement and unclosed earnings, so it is counted exactly once.
            ...($midyear ? [['accountId' => $accounts['4010']->id, 'debit' => '', 'credit' => '20000.00']] : []),
        ], $preparerId);
        $books->approveOpening($reviewerId);
        if ($midyear) {
            $books->saveYtdSummary($today->year, $today->subDay()->toDateString(), 'DEMO ONLY: synthetic pre-cutover income and expenses', [
                ['accountId' => $accounts['4010']->id, 'amount' => '100000.00'],
                ['accountId' => $accounts['6130']->id, 'amount' => '80000.00'],
            ], $preparerId);
            $books->approveYtdSummary($reviewerId);
        }
    }

    private function purchasesAndPayments(string $date, array $items, array $accounts, array $suppliers, int $preparerId, int $reviewerId): void
    {
        $purchases = app(SupplierPurchaseService::class);
        $invoice = $purchases->saveDraft([
            'supplier_id' => $suppliers[0]->id, 'invoice_number' => 'DEMO-PUR-001', 'recognition_date' => $date,
            'due_date' => CarbonImmutable::parse($date)->addDays(30)->toDateString(), 'description' => 'DEMO steel purchase on credit',
            'terms' => 'Net 30', 'receipt_confirmed' => true,
            'lines' => [['inventory_id' => $items['steel']->id, 'accounting_account_id' => '',
                'description' => '20 steel bars, demo receiving evidence', 'quantity' => '20.00', 'amount' => '3000.00']],
        ], $preparerId);
        $purchases->post($invoice->id, $reviewerId);
        $purchases->saveDraft([
            'supplier_id' => $suppliers[1]->id, 'invoice_number' => 'DEMO-PUR-DRAFT', 'recognition_date' => $date,
            'due_date' => CarbonImmutable::parse($date)->addDays(15)->toDateString(), 'description' => 'DEMO purchase awaiting receipt review',
            'terms' => 'Net 15', 'receipt_confirmed' => false,
            'lines' => [['inventory_id' => $items['doors']->id, 'accounting_account_id' => '',
                'description' => 'Two demo doors', 'quantity' => '2.00', 'amount' => '3000.00']],
        ], $preparerId);
        $payments = app(CashDisbursementService::class);
        $payment = $payments->saveDraft([
            'supplierId' => (string) $suppliers[0]->id, 'payee' => $suppliers[0]->name, 'paymentDate' => $date, 'method' => 'Bank Transfer',
            'moneyAccountId' => (string) $accounts['1020']->id, 'reference' => 'DEMO-PAY-001', 'checkNumber' => '',
            'description' => 'DEMO partial payment of purchase invoice', 'evidenceReference' => 'DEMO ONLY transfer 001',
            'receiptConfirmed' => false, 'amount' => '1000.00',
            'allocations' => [['invoice_id' => 'purchase:'.$invoice->id, 'description' => 'Partial settlement', 'amount' => '1000.00']],
        ], $preparerId);
        $payments->post($payment->id, $reviewerId);
        $expense = [
            'payee' => 'DEMO Utilities Provider', 'paymentDate' => $date, 'method' => 'Cash',
            'moneyAccountId' => (string) $accounts['1010']->id, 'reference' => 'DEMO-EXP-001', 'checkNumber' => '',
            'description' => 'DEMO utility expense', 'evidenceReference' => 'DEMO ONLY utility bill', 'amount' => '1500.00',
            'allocations' => [['accounting_account_id' => (string) $accounts['6130']->id, 'description' => 'Demo utility service', 'amount' => '1500.00']],
        ];
        $disbursement = $payments->saveDraft($expense, $preparerId);
        $payments->post($disbursement->id, $reviewerId);
        $expense['reference'] = 'DEMO-EXP-DRAFT';
        $expense['description'] = 'DEMO expense awaiting review';
        $payments->saveDraft($expense, $preparerId);
    }

    private function demolition(string $date, array $items, int $actorId): void
    {
        foreach (['DEMO Warehouse Dismantling', 'DEMO Residential Recovery'] as $index => $name) {
            $project = DemolitionProject::create(['name' => $name, 'location' => 'Synthetic site, Rizal', 'start_date' => $date]);
            $recovery = $project->recoveredMaterials()->create([
                'material' => 'DEMO Timber Boards', 'condition' => 'Mixed; inspect before resale', 'quantity' => '5.00',
                'unit' => 'piece', 'notes' => 'Synthetic recovered-material example', 'recorded_by' => $actorId,
            ]);
            if ($index === 0) {
                app(AssessRecoveredMaterial::class)->handle($recovery->id, [
                    'accepted_quantity' => '4.00', 'rejected_quantity' => '1.00', 'rejection_reason' => 'DEMO damaged board',
                    'assigned_unit_value' => '200.00', 'create_inventory' => false, 'inventory_id' => $items['lumber']->id,
                ], $actorId);
            }
        }
    }

    private function sales(array $items, Employee $cashier): void
    {
        Auth::setUser($cashier);
        foreach (['cash' => ['steel', '560.00', 2], 'card' => ['doors', '2800.00', 1],
            'bank_transfer' => ['wire', '1008.00', 1]] as $method => [$key, $received, $quantity]) {
            $pos = app('livewire')->new(PointOfSale::class);
            $pos->customerName = 'DEMO '.$method.' Customer';
            $pos->paymentMethod = $method;
            $pos->paymentReference = $method === 'cash' ? '' : 'DEMO-TENDER-'.$method;
            $pos->amountReceived = $received;
            $pos->addToCart($items[$key]->id);
            $pos->setQuantity($items[$key]->id, $quantity);
            $pos->checkout();
        }
    }

    private function journals(string $date, array $accounts, int $preparerId, int $reviewerId): void
    {
        $journals = app(ManualJournalService::class);
        $input = [
            'accountingDate' => $date, 'reference' => 'DEMO-JV-DRAFT', 'externalReference' => 'DEMO ONLY',
            'description' => 'DEMO balanced manual journal awaiting posting',
            'lines' => [
                ['accountId' => (string) $accounts['6130']->id, 'debit' => '250.00', 'credit' => ''],
                ['accountId' => (string) $accounts['1020']->id, 'debit' => '', 'credit' => '250.00'],
            ],
        ];
        $journals->saveDraft($input, $preparerId);
        $input['reference'] = 'DEMO-JV-UNBALANCED';
        $input['description'] = 'DEMO unequal totals: correct before posting';
        $input['lines'][1]['credit'] = '249.99';
        $journals->saveDraft($input, $preparerId);
        $input['reference'] = 'DEMO-SETUP-COMPLETE';
        $input['description'] = 'DEMO ONLY: bank charge and completed synthetic setup';
        $input['lines'][1]['credit'] = '250.00';
        $draft = $journals->saveDraft($input, $preparerId);
        $journals->submitForReview($draft->id, $preparerId);
        $journals->approveDraft($draft->id, $reviewerId);
        $journals->postDraft($draft->id, $reviewerId);
    }
}
