<?php

namespace App\Support;

/**
 * Illustrative VAT values shared by Tax Compliance demonstration pages.
 * These fixtures are deliberately separate from sales and business records.
 */
final class VatDemonstrationData
{
    public static function records(): array
    {
        return [
            ['reference' => 'VAT-2026-08-001', 'date' => '2026-08-05', 'period' => 'Q3 2026', 'taxable' => 12500.00, 'vat' => 1500.00, 'total' => 14000.00],
            ['reference' => 'VAT-2026-08-002', 'date' => '2026-08-12', 'period' => 'Q3 2026', 'taxable' => 18600.00, 'vat' => 2232.00, 'total' => 20832.00],
            ['reference' => 'VAT-2026-08-003', 'date' => '2026-08-20', 'period' => 'Q3 2026', 'taxable' => 9500.00, 'vat' => 1140.00, 'total' => 10640.00],
            ['reference' => 'VAT-2026-07-001', 'date' => '2026-07-15', 'period' => 'Q3 2026', 'taxable' => 14200.00, 'vat' => 1704.00, 'total' => 15904.00],
            ['reference' => 'VAT-2026-06-001', 'date' => '2026-06-18', 'period' => 'Q2 2026', 'taxable' => 11800.00, 'vat' => 1416.00, 'total' => 13216.00],
            ['reference' => 'VAT-2026-05-001', 'date' => '2026-05-22', 'period' => 'Q2 2026', 'taxable' => 10200.00, 'vat' => 1224.00, 'total' => 11424.00],
            ['reference' => 'VAT-2026-03-001', 'date' => '2026-03-11', 'period' => 'Q1 2026', 'taxable' => 8700.00, 'vat' => 1044.00, 'total' => 9744.00],
        ];
    }

    public static function summaries(): array
    {
        return [
            'monthly' => [
                'August 2026' => ['taxable' => 40600.00, 'vat' => 4872.00, 'total' => 45472.00],
                'July 2026' => ['taxable' => 14200.00, 'vat' => 1704.00, 'total' => 15904.00],
                'June 2026' => ['taxable' => 11800.00, 'vat' => 1416.00, 'total' => 13216.00],
            ],
            'quarterly' => [
                'Q3 2026' => ['taxable' => 54800.00, 'vat' => 6576.00, 'total' => 61376.00],
                'Q2 2026' => ['taxable' => 22000.00, 'vat' => 2640.00, 'total' => 24640.00],
                'Q1 2026' => ['taxable' => 8700.00, 'vat' => 1044.00, 'total' => 9744.00],
            ],
        ];
    }

    public static function taxPeriods(): array
    {
        return ['Q1 2026', 'Q2 2026', 'Q3 2026'];
    }
}
