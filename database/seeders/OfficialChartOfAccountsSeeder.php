<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Support\RolePermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OfficialChartOfAccountsSeeder extends Seeder
{
    /**
     * The approved FCDC chart of accounts. Codes, titles, types, and descriptions
     * follow the official account table supplied for this application.
     */
    public const ACCOUNTS = [
        ['1010', 'Cash on Hand', 'Asset', 'cash', 'debit', 'Cash received from completed POS sales paid in cash.'],
        ['1020', 'Cash in Bank', 'Asset', 'bank', 'debit', 'Company funds recorded in bank accounts, including documented deposits and payments.'],
        ['1030', 'Input VAT', 'Asset', 'input_vat', 'debit', 'Qualified input VAT from eligible purchases and expenses supported by valid documents.'],
        ['1040', 'Recovered Materials Inventory', 'Asset', 'inventory', 'debit', 'Recorded cost of recovered construction materials that remain available for resale.'],
        ['1050', 'Cleaning and Reworking Supplies Inventory', 'Asset', 'inventory', 'debit', 'Cost of unused cleaning and reworking supplies held for future use, if separately tracked.'],
        ['2010', 'Accounts Payable', 'Liability', 'accounts_payable', 'credit', 'Unpaid obligations to suppliers or service providers supported by recorded transactions.'],
        ['2020', 'Output VAT Payable', 'Liability', 'output_vat', 'credit', 'Output VAT collected or collectible from applicable VATable POS sales.'],
        ['3010', 'Share Capital', 'Equity', 'capital', 'credit', "Owner's or shareholders' capital recorded in the company's accounting records."],
        ['3020', 'Retained Earnings', 'Equity', 'retained_earnings', 'credit', 'Accumulated profits or losses from previous accounting periods.'],
        ['4010', 'Recovered Materials Sales Revenue', 'Revenue', 'sales', 'credit', 'Revenue from completed POS sales of recovered construction materials, excluding output VAT.'],
        ['4020', 'Sales Returns and Allowances', 'Revenue', 'contra_revenue', 'debit', 'Approved sales returns and allowances that reduce recorded sales revenue.'],
        ['4030', 'Sales Discounts', 'Revenue', 'contra_revenue', 'debit', 'Authorized discounts granted to customers that reduce sales revenue.'],
        ['5010', 'Cost of Recovered Materials Sold', 'Expense', 'cost_of_goods_sold', 'debit', 'Recorded inventory cost of recovered materials recognized when the corresponding materials are sold.'],
        ['6010', 'Reworking Costs', 'Expense', 'operating_expense', 'debit', 'Recorded costs of cleaning, repairing, and preparing recovered materials for resale, classified according to the adopted costing policy.'],
        ['6120', 'Rent Expense', 'Expense', 'operating_expense', 'debit', 'Recorded rental costs for business premises or storage areas.'],
        ['6130', 'Utilities Expense', 'Expense', 'operating_expense', 'debit', 'Recorded electricity, water, and other utility expenses.'],
        ['6140', 'Repairs and Maintenance Expense', 'Expense', 'operating_expense', 'debit', 'Repair and maintenance costs not included in inventory costs.'],
        ['6150', 'Office Supplies Expense', 'Expense', 'operating_expense', 'debit', 'Cost of office supplies consumed for business operations.'],
        ['6160', 'Cleaning Supplies Expense', 'Expense', 'operating_expense', 'debit', 'Cost of cleaning supplies consumed and recognized as an expense rather than inventory.'],
        ['6170', 'Transportation and Delivery Expense', 'Expense', 'operating_expense', 'debit', 'Transportation and delivery costs not included in inventory costs.'],
        ['6180', 'Other Operating Expenses', 'Expense', 'operating_expense', 'debit', 'Other documented operating expenses that do not belong to a more specific expense account.'],
    ];

    /**
     * Official account code => posting source mapping.
     *
     * Card settlements are deposited to Cash in Bank because the official chart
     * does not contain a separate card-clearing account.
     *
     * @var array<string, string>
     */
    public const POSTING_MAPPINGS = [
        'cash' => '1010',
        'bank' => '1020',
        'card_clearing' => '1020',
        'sales' => '4010',
        'output_vat' => '2020',
        'cogs' => '5010',
        'inventory' => '1040',
        'accounts_payable' => '2010',
        'recovery_offset' => '6010',
        'adjustment' => '6180',
    ];

    /**
     * @return array{created: list<string>, updated: list<string>, exact_matches: list<string>, conflicts: array<string, mixed>, preserved_unrelated: list<array{code: string, name: string}>, legacy_accounts: list<array{original: string, legacy: string}>}
     */
    public function seed(?int $approvedBy = null): array
    {
        return DB::transaction(function () use ($approvedBy): array {
            $now = now();
            $approvedBy ??= $this->defaultApproverId();
            $officialCodes = array_column(self::ACCOUNTS, 0);
            $created = [];
            $updated = [];
            $exactMatches = [];
            $legacyAccounts = [];

            foreach (self::ACCOUNTS as [$code, $name, $type, $classification, $normalBalance, $description]) {
                $definition = [
                    'name' => $name,
                    'description' => $description,
                    'type' => $type,
                    'classification' => $classification,
                    'normal_balance' => $normalBalance,
                ];

                $existing = DB::table('accounting_accounts')
                    ->where('code', $code)
                    ->lockForUpdate()
                    ->first();

                if ($existing === null) {
                    DB::table('accounting_accounts')->insert([
                        'code' => $code,
                        ...$definition,
                        'is_active' => true,
                        'approved_at' => $now,
                        'approved_by' => $approvedBy,
                        'used_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $created[] = $code;

                    continue;
                }

                $identityMatches = $existing->name === $name
                    && $existing->type === $type
                    && $existing->classification === $classification
                    && $existing->normal_balance === $normalBalance;

                if ($existing->used_at !== null && ! $identityMatches) {
                    // A used account cannot be repurposed without rewriting the meaning of
                    // historical journal lines. Preserve it under a unique legacy code and
                    // create the official account separately.
                    $legacyCode = $this->legacyCode($code);
                    DB::table('accounting_accounts')->where('id', $existing->id)->update([
                        'code' => $legacyCode,
                        'name' => Str::limit($existing->name, 140).' (Legacy)',
                        'is_active' => false,
                        'approved_at' => null,
                        'approved_by' => null,
                        'updated_at' => $now,
                    ]);
                    $legacyAccounts[] = ['original' => $code, 'legacy' => $legacyCode];

                    DB::table('accounting_accounts')->insert([
                        'code' => $code,
                        ...$definition,
                        'is_active' => true,
                        'approved_at' => $now,
                        'approved_by' => $approvedBy,
                        'used_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $created[] = $code;

                    continue;
                }

                DB::table('accounting_accounts')->where('id', $existing->id)->update([
                    ...$definition,
                    'is_active' => true,
                    'approved_at' => $now,
                    'approved_by' => $approvedBy,
                    'updated_at' => $now,
                ]);

                if ($identityMatches) {
                    $exactMatches[] = $code;
                } else {
                    $updated[] = $code;
                }
            }

            // Retire every non-official account from the active chart. Rows are kept
            // because journal lines and audit trails still reference them.
            DB::table('accounting_accounts')
                ->whereNotIn('code', $officialCodes)
                ->update(['is_active' => false, 'updated_at' => $now]);

            $accountIds = DB::table('accounting_accounts')
                ->whereIn('code', $officialCodes)
                ->pluck('id', 'code');

            foreach (self::POSTING_MAPPINGS as $source => $code) {
                $accountId = $accountIds->get($code);
                if ($accountId === null) {
                    continue;
                }

                $mapping = DB::table('accounting_posting_mappings')->where('source', $source)->first();
                if ($mapping === null) {
                    DB::table('accounting_posting_mappings')->insert([
                        'source' => $source,
                        'accounting_account_id' => $accountId,
                        'approved_at' => $now,
                        'approved_by' => $approvedBy,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    continue;
                }

                if ((int) $mapping->accounting_account_id !== (int) $accountId || $mapping->approved_at === null) {
                    DB::table('accounting_posting_mappings')->where('id', $mapping->id)->update([
                        'accounting_account_id' => $accountId,
                        'approved_at' => $now,
                        'approved_by' => $approvedBy,
                        'updated_at' => $now,
                    ]);
                }
            }

            return [
                'created' => $created,
                'updated' => $updated,
                'exact_matches' => $exactMatches,
                'conflicts' => [],
                'preserved_unrelated' => $this->unrelatedAccounts($officialCodes),
                'legacy_accounts' => $legacyAccounts,
            ];
        });
    }

    public function run(): void
    {
        $result = $this->seed();

        if ($this->command === null) {
            return;
        }

        $this->command->info('Official chart accounts created: '.count($result['created']).' ['.implode(', ', $result['created']).']');
        $this->command->info('Official chart accounts updated: '.count($result['updated']).' ['.implode(', ', $result['updated']).']');
        $this->command->info('Official chart accounts unchanged: '.count($result['exact_matches']).' ['.implode(', ', $result['exact_matches']).']');

        foreach ($result['legacy_accounts'] as $legacy) {
            $this->command->warn("Used account {$legacy['original']} was preserved as {$legacy['legacy']} for historical reporting.");
        }
    }

    private function defaultApproverId(): ?int
    {
        if (! DB::getSchemaBuilder()->hasTable('employees')) {
            return null;
        }

        return Employee::query()
            ->whereHas('roles', fn ($query) => $query->where('name', RolePermissionCatalog::ADMIN_ROLE))
            ->orderBy('id')
            ->value('id')
            ?? Employee::query()->orderBy('id')->value('id');
    }

    private function legacyCode(string $code): string
    {
        $base = 'LEGACY-'.$code;
        $candidate = $base;
        $suffix = 2;

        while (DB::table('accounting_accounts')->where('code', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /** @return list<array{code: string, name: string}> */
    private function unrelatedAccounts(array $officialCodes): array
    {
        return DB::table('accounting_accounts')
            ->whereNotIn('code', $officialCodes)
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(fn (object $account): array => ['code' => $account->code, 'name' => $account->name])
            ->all();
    }
}
