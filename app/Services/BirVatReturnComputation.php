<?php

namespace App\Services;

/**
 * Computes the BIR Form 2550Q VAT return items used by the preparation page.
 *
 * POS output VAT and Reworking Cost Record input VAT come from the same saved
 * records as the rest of the VAT compliance module. The remaining 2550Q items
 * are adjustments that must be entered by the preparer because they are not in
 * the POS or inventory source records.
 */
class BirVatReturnComputation
{
    /**
     * Adjustment fields saved per return period.
     *
     * @var array<int, string>
     */
    public const MANUAL_AMOUNT_FIELDS = [
        'output_vat_uncollected_receivables',
        'output_vat_recovered_receivables',
        'input_tax_carried_over',
        'deferred_capital_goods_input_tax_prior',
        'transitional_input_tax',
        'presumptive_input_tax',
        'other_input_tax',
        'deferred_capital_goods_input_tax_current',
        'input_tax_vat_exempt_sales',
        'vat_refund_tcc_claimed',
        'input_vat_unpaid_payables',
        'other_input_tax_deductions',
        'input_vat_settled_unpaid_payables',
    ];

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $adjustments
     * @return array<string, string|bool>
     */
    public function compute(array $summary, array $adjustments = []): array
    {
        $item31a = $this->cents($summary['taxable_sales'] ?? 0);
        $item31b = $this->cents($summary['output_vat'] ?? 0);
        $item32a = $this->cents($summary['zero_rated_sales'] ?? 0);
        $item33a = $this->cents($summary['exempt_sales'] ?? 0);

        $item34a = $item31a + $item32a + $item33a;
        $item34b = $item31b;

        $item35b = $this->cents($adjustments['output_vat_uncollected_receivables'] ?? 0);
        $item36b = $this->cents($adjustments['output_vat_recovered_receivables'] ?? 0);
        $item37b = $item34b - $item35b + $item36b;

        $item38b = $this->cents($adjustments['input_tax_carried_over'] ?? 0);
        $item39b = $this->cents($adjustments['deferred_capital_goods_input_tax_prior'] ?? 0);
        $item40b = $this->cents($adjustments['transitional_input_tax'] ?? 0);
        $item41b = $this->cents($adjustments['presumptive_input_tax'] ?? 0);
        $item42b = $this->cents($adjustments['other_input_tax'] ?? 0);
        $item43b = $item38b + $item39b + $item40b + $item41b + $item42b;

        $item50a = $this->cents($summary['input_vat_reworking_cost'] ?? 0);
        $item50b = $this->cents($summary['input_vat'] ?? 0);
        $item51b = $item43b + $item50b;

        $item52b = $this->cents($adjustments['deferred_capital_goods_input_tax_current'] ?? 0);
        $item53b = $this->cents($adjustments['input_tax_vat_exempt_sales'] ?? 0);
        $item54b = $this->cents($adjustments['vat_refund_tcc_claimed'] ?? 0);
        $item55b = $this->cents($adjustments['input_vat_unpaid_payables'] ?? 0);
        $item56b = $this->cents($adjustments['other_input_tax_deductions'] ?? 0);
        $item57b = $item52b + $item53b + $item54b + $item55b + $item56b;
        $item58b = $this->cents($adjustments['input_vat_settled_unpaid_payables'] ?? 0);
        $item59b = $item57b + $item58b;

        $item60b = $item51b - $item59b;
        $item61 = $item37b - $item60b;

        return [
            'item_31a_taxable_sales' => $this->formatCents($item31a),
            'item_31b_output_vat' => $this->formatCents($item31b),
            'item_32a_zero_rated_sales' => $this->formatCents($item32a),
            'item_33a_exempt_sales' => $this->formatCents($item33a),
            'item_34a_total_sales' => $this->formatCents($item34a),
            'item_34b_output_tax' => $this->formatCents($item34b),
            'item_35b_output_vat_uncollected_receivables' => $this->formatCents($item35b),
            'item_36b_output_vat_recovered_receivables' => $this->formatCents($item36b),
            'item_37b_total_adjusted_output_tax' => $this->formatCents($item37b),
            'item_38b_input_tax_carried_over' => $this->formatCents($item38b),
            'item_39b_deferred_capital_goods_input_tax_prior' => $this->formatCents($item39b),
            'item_40b_transitional_input_tax' => $this->formatCents($item40b),
            'item_41b_presumptive_input_tax' => $this->formatCents($item41b),
            'item_42b_other_input_tax' => $this->formatCents($item42b),
            'item_43b_total_prior_input_tax' => $this->formatCents($item43b),
            'item_50a_total_current_purchases' => $this->formatCents($item50a),
            'item_50b_total_current_input_tax' => $this->formatCents($item50b),
            'item_51b_total_available_input_tax' => $this->formatCents($item51b),
            'item_52b_deferred_capital_goods_input_tax_current' => $this->formatCents($item52b),
            'item_53b_input_tax_vat_exempt_sales' => $this->formatCents($item53b),
            'item_54b_vat_refund_tcc_claimed' => $this->formatCents($item54b),
            'item_55b_input_vat_unpaid_payables' => $this->formatCents($item55b),
            'item_56b_other_input_tax_deductions' => $this->formatCents($item56b),
            'item_57b_total_input_tax_deductions' => $this->formatCents($item57b),
            'item_58b_input_vat_settled_unpaid_payables' => $this->formatCents($item58b),
            'item_59b_adjusted_input_tax_deductions' => $this->formatCents($item59b),
            'item_60b_total_allowable_input_tax' => $this->formatCents($item60b),
            'item_61_net_vat_payable' => $this->formatCents($item61),
            'item_61_is_excess_input_tax' => $item61 < 0,
            'memo_total_sales_inclusive' => $this->formatCents($this->cents($summary['total_sales'] ?? 0)),
            'memo_non_vat_sales' => $this->formatCents($this->cents($summary['non_vat_sales'] ?? 0)),
        ];
    }

    private function cents(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round((float) $amount * 100);
    }

    private function formatCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
