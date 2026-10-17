<?php

namespace Database\Seeders;

use App\Support\DemoDatabaseIsolation;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProposedChartOfAccountsSeeder extends Seeder
{
    private const ACCOUNTS = [
        ['1010', 'Cash on Hand', 'Asset', 'cash', 'debit', 'Assets — 1000', 'Ordinary proposed account'],
        ['1020', 'Cash in Bank', 'Asset', 'bank', 'debit', 'Assets — 1000', 'Ordinary proposed account'],
        ['1030', 'Accounts Receivable', 'Asset', 'accounts_receivable', 'debit', 'Assets — 1000', 'Preserve GL-only receivable scope'],
        ['1040', 'Input VAT', 'Asset', 'input_vat', 'debit', 'Assets — 1000', 'No automatic purchase-VAT eligibility'],
        ['1050', 'Inventory — Recovered Construction Materials', 'Asset', 'inventory', 'debit', 'Assets — 1000', 'No new category routing'],
        ['1060', 'Inventory — Other Resale Materials', 'Asset', 'inventory', 'debit', 'Assets — 1000', 'No new category routing'],
        ['1070', 'Prepaid Expenses', 'Asset', 'other_current_asset', 'debit', 'Assets — 1000', 'Validate actual treatment before use'],
        ['1080', 'Advances to Employees/Suppliers', 'Asset', 'other_current_asset', 'debit', 'Assets — 1000', 'No supplier-advance application workflow'],
        ['1500', 'Property and Equipment', 'Asset', 'equipment', 'debit', 'Assets — 1000', 'No asset-management expansion'],
        ['1510', 'Accumulated Depreciation', 'Asset', 'contra_asset', 'credit', 'Assets — 1000', 'Contra-asset; credit balance reduces total assets'],
        ['2010', 'Accounts Payable', 'Liability', 'accounts_payable', 'credit', 'Liabilities — 2000', 'Preserve AP control-account rules'],
        ['2020', 'Accrued Expenses Payable', 'Liability', 'other_current_liability', 'credit', 'Liabilities — 2000', 'Validate actual accrual treatment'],
        ['2030', 'Output VAT', 'Liability', 'output_vat', 'credit', 'Liabilities — 2000', 'Existing singular POS VAT role'],
        ['2040', 'VAT Payable', 'Liability', 'other_current_liability', 'credit', 'Liabilities — 2000', 'Provisional; distinct use and settlement policy pending'],
        ['2050', 'Salaries and Wages Payable', 'Liability', 'other_current_liability', 'credit', 'Liabilities — 2000', 'Validate applicable payroll liability'],
        ['2060', 'Other Taxes Payable', 'Liability', 'other_current_liability', 'credit', 'Liabilities — 2000', 'If applicable'],
        ['2070', 'Customer Advances', 'Liability', 'other_current_liability', 'credit', 'Liabilities — 2000', 'If applicable; no new advance module'],
        ['2080', 'Loans Payable', 'Liability', 'other_noncurrent_liability', 'credit', 'Liabilities — 2000', 'Provisional; if applicable; maturity classification pending actual terms'],
        ['3010', 'Capital Stock', 'Equity', 'capital', 'credit', 'Equity — 3000', 'Validate corporate records'],
        ['3020', 'Additional Paid-in Capital', 'Equity', 'other_equity', 'credit', 'Equity — 3000', 'If applicable'],
        ['3030', 'Retained Earnings', 'Equity', 'retained_earnings', 'credit', 'Equity — 3000', 'Preserve existing fiscal-year close role'],
        ['3040', 'Current-Year Profit or Loss', 'Equity', 'other_equity', 'variable', 'Equity — 3000', 'Credit when there is a profit; debit when there is a loss; derived and unavailable for ledger posting'],
        ['3050', 'Dividends Declared', 'Equity', 'contra_equity', 'debit', 'Equity — 3000', 'Contra-equity; if applicable; subject to approved corporate policy'],
        ['4010', 'Sales — Recovered Construction Materials', 'Revenue', 'sales', 'credit', 'Revenue — 4000', 'No new category routing'],
        ['4020', 'Sales — Other Materials', 'Revenue', 'sales', 'credit', 'Revenue — 4000', 'No new category routing'],
        ['4030', 'Demolition Service Revenue', 'Revenue', 'sales', 'credit', 'Revenue — 4000', 'Provisional operating-revenue grouping; if applicable'],
        ['4040', 'Other Operating Revenue', 'Revenue', 'sales', 'credit', 'Revenue — 4000', 'Validate operating-revenue grouping before use'],
        ['4090', 'Sales Returns and Allowances', 'Revenue', 'contra_revenue', 'debit', 'Revenue — 4000', 'Contra-revenue; preserve net-sales calculation'],
        ['5010', 'Cost of Goods Sold — Recovered Materials', 'Expense', 'cost_of_goods_sold', 'debit', 'Cost of Goods Sold — 5000', 'No new category routing'],
        ['5020', 'Cost of Goods Sold — Other Materials', 'Expense', 'cost_of_goods_sold', 'debit', 'Cost of Goods Sold — 5000', 'No new category routing'],
        ['5030', 'Approved Rework Costs Included in Inventory Cost', 'Expense', 'cost_of_goods_sold', 'debit', 'Cost of Goods Sold — 5000', 'Provisional display/storage only; if applicable; capitalization versus expense policy unresolved'],
        ['5090', 'Inventory Adjustments / Shrinkage', 'Expense', 'other_expense', 'debit', 'Cost of Goods Sold — 5000', 'Subject to approval; not every adjustment is shrinkage'],
        ['6010', 'Salaries and Wages Expense', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'Ordinary proposed account'],
        ['6020', 'Rent Expense', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'Ordinary proposed account'],
        ['6030', 'Utilities Expense', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'Ordinary proposed account'],
        ['6040', 'Transportation and Delivery Expense', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'Ordinary proposed account'],
        ['6050', 'Repairs and Maintenance Expense', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'Ordinary proposed account'],
        ['6060', 'Fuel and Lubricants Expense', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'Ordinary proposed account'],
        ['6070', 'Tools and Supplies Expense', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'No automatic capitalization decision'],
        ['6080', 'Equipment Maintenance Expense', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'Ordinary proposed account'],
        ['6090', 'Communication and Internet Expense', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'Ordinary proposed account'],
        ['6100', 'Office Supplies Expense', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'No automatic capitalization decision'],
        ['6110', 'Depreciation Expense', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'No depreciation schedule or automation'],
        ['6120', 'Permits and Licenses Expense', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'Ordinary proposed account'],
        ['6130', 'Professional Fees', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'Ordinary proposed account'],
        ['6140', 'Bank Charges', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'Demo operating-expense grouping; validate policy before use'],
        ['6150', 'Insurance Expense', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'No automatic prepaid-versus-incurred decision'],
        ['6160', 'Other Taxes and Fees Expense', 'Expense', 'operating_expense', 'debit', 'Operating Expenses — 6000', 'As applicable; distinguish from VAT/payables'],
        ['6190', 'Miscellaneous Expense', 'Expense', 'other_expense', 'debit', 'Operating Expenses — 6000', 'Restricted incidental use; posting blocked while review policy unresolved'],
        ['7010', 'Other Income', 'Revenue', 'other_income', 'credit', 'Other Income and Expenses — 7000', 'Ordinary proposed account'],
        ['7020', 'Interest Income', 'Revenue', 'other_income', 'credit', 'Other Income and Expenses — 7000', 'Ordinary proposed account'],
        ['7030', 'Interest Expense', 'Expense', 'other_expense', 'debit', 'Other Income and Expenses — 7000', 'Ordinary proposed account'],
        ['7040', 'Loss on Disposal of Equipment', 'Expense', 'other_expense', 'debit', 'Other Income and Expenses — 7000', 'If applicable; no disposal workflow added'],
        ['7090', 'Other Non-Operating Expenses', 'Expense', 'other_expense', 'debit', 'Other Income and Expenses — 7000', 'Ordinary proposed account'],
    ];

    private const POLICY_HELD_CODES = ['1510', '2040', '3040', '3050', '5030', '6190'];

    /**
     * @return array{created: list<string>, exact_matches: list<string>, conflicts: array<string, array<string, array{expected: mixed, existing: mixed}>>, preserved_unrelated: list<array{code: string, name: string}>}
     */
    public function seed(DemoDatabaseIsolation $isolation): array
    {
        $isolation->verify();

        return DB::transaction(function (): array {
            $codes = array_column(self::ACCOUNTS, 0);
            $existing = DB::table('accounting_accounts')
                ->whereIn('code', $codes)
                ->lockForUpdate()
                ->get()
                ->keyBy('code');

            $created = [];
            $exactMatches = [];
            $conflicts = [];
            $expectedByCode = [];

            foreach (self::ACCOUNTS as [$code, $name, $type, $classification, $normalBalance, $category, $pendingTreatment]) {
                $description = "Proposed category: {$category}. Pending treatment: {$pendingTreatment}.";
                $definition = [
                    'name' => $name,
                    'description' => $description,
                    'type' => $type,
                    'classification' => $classification,
                    'normal_balance' => $normalBalance,
                ];
                $expectedByCode[$code] = $definition;
                $account = $existing->get($code);

                if ($account === null) {
                    continue;
                }

                $differences = [];
                foreach ($definition as $field => $expected) {
                    if ($account->{$field} !== $expected) {
                        $differences[$field] = ['expected' => $expected, 'existing' => $account->{$field}];
                    }
                }
                if (in_array($code, self::POLICY_HELD_CODES, true)
                    && ($account->is_active || $account->approved_at !== null || $account->approved_by !== null || $account->used_at !== null)) {
                    $differences['operational_state'] = [
                        'expected' => 'inactive and unused, without approver or approval timestamp',
                        'existing' => [
                            'is_active' => (bool) $account->is_active,
                            'approved_at' => $account->approved_at,
                            'approved_by' => $account->approved_by,
                            'used_at' => $account->used_at,
                        ],
                    ];
                }

                if ($differences === []) {
                    $exactMatches[] = $code;
                } else {
                    $conflicts[$code] = $differences;
                }
            }

            if ($conflicts !== []) {
                return [
                    'created' => [],
                    'exact_matches' => $exactMatches,
                    'conflicts' => $conflicts,
                    'preserved_unrelated' => $this->unrelatedAccounts($codes),
                ];
            }

            $now = now();
            foreach (self::ACCOUNTS as [$code]) {
                if ($existing->has($code)) {
                    continue;
                }

                DB::table('accounting_accounts')->insert([
                    'code' => $code,
                    ...$expectedByCode[$code],
                    'is_active' => false,
                    'approved_at' => null,
                    'approved_by' => null,
                    'used_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $created[] = $code;
            }

            return [
                'created' => $created,
                'exact_matches' => $exactMatches,
                'conflicts' => [],
                'preserved_unrelated' => $this->unrelatedAccounts($codes),
            ];
        });
    }

    public function run(DemoDatabaseIsolation $isolation): void
    {
        $result = $this->seed($isolation);

        if ($this->command === null) {
            if ($result['conflicts'] !== []) {
                throw new \RuntimeException('Conflicting proposed account definitions were found; no proposed accounts were changed or inserted.');
            }

            return;
        }

        if (! $this->reportResult($result, $this->command)) {
            throw new \RuntimeException('Conflicting proposed account definitions were found; no proposed accounts were changed or inserted.');
        }
    }

    public function reportResult(array $result, Command $command): bool
    {
        $command->info('Created: '.count($result['created']).' ['.implode(', ', $result['created']).']');
        $command->info('Exact matches: '.count($result['exact_matches']).' ['.implode(', ', $result['exact_matches']).']');
        $command->info('Preserved unrelated/supporting accounts: '.count($result['preserved_unrelated']));
        foreach ($result['preserved_unrelated'] as $account) {
            $command->line($account['code'].' '.$account['name']);
        }

        foreach ($result['conflicts'] as $code => $fields) {
            foreach ($fields as $field => $values) {
                $command->error($code.' '.$field.': expected '.json_encode($values['expected']).'; found '.json_encode($values['existing']));
            }
        }

        if ($result['conflicts'] !== []) {
            $command->error('Conflicts found; no proposed account was changed or inserted.');

            return false;
        }

        return true;
    }

    /** @return list<array{code: string, name: string}> */
    private function unrelatedAccounts(array $codes): array
    {
        return DB::table('accounting_accounts')
            ->whereNotIn('code', $codes)
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(fn (object $account): array => ['code' => $account->code, 'name' => $account->name])
            ->all();
    }
}
