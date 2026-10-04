<?php

namespace App\Support;

/**
 * Illustrative account fixtures shared by Accounting demonstration pages.
 * These values are not an approved chart or persisted account records.
 */
final class ReferenceAccounts
{
    public static function all(): array
    {
        return [
            ['code' => '1010', 'name' => 'Cash', 'type' => 'Asset', 'description' => 'Cash and cash equivalents', 'status' => 'Active'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'Asset', 'description' => 'Customer receivables', 'status' => 'Active'],
            ['code' => '1200', 'name' => 'Inventory', 'type' => 'Asset', 'description' => 'Construction materials', 'status' => 'Active'],
            ['code' => '2010', 'name' => 'Accounts Payable', 'type' => 'Liability', 'description' => 'Supplier obligations', 'status' => 'Active'],
            ['code' => '3010', 'name' => "Owner's Capital", 'type' => 'Equity', 'description' => "Owner's equity", 'status' => 'Active'],
            ['code' => '4010', 'name' => 'Sales Revenue', 'type' => 'Revenue', 'description' => 'Material sales revenue', 'status' => 'Active'],
            ['code' => '5010', 'name' => 'Cost of Goods Sold', 'type' => 'Expense', 'description' => 'Cost of materials sold', 'status' => 'Active'],
            ['code' => '5020', 'name' => 'Operating Expense', 'type' => 'Expense', 'description' => 'Operating expenses', 'status' => 'Active'],
        ];
    }
}
