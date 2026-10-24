<?php

use App\Services\BirVatReturnComputation;

test('BIR 2550Q computation includes only VAT sales lines in item 34 and carries the manual adjustments through item 61', function () {
    $items = (new BirVatReturnComputation)->compute([
        'taxable_sales' => '1000.00',
        'zero_rated_sales' => '200.00',
        'exempt_sales' => '300.00',
        'non_vat_sales' => '400.00',
        'output_vat' => '120.00',
        'total_sales' => '2020.00',
        'input_vat_reworking_cost' => '500.00',
        'input_vat' => '60.00',
    ], [
        'output_vat_uncollected_receivables' => '20.00',
        'output_vat_recovered_receivables' => '5.00',
        'input_tax_carried_over' => '10.00',
        'deferred_capital_goods_input_tax_prior' => '2.00',
        'transitional_input_tax' => '1.00',
        'presumptive_input_tax' => '3.00',
        'other_input_tax' => '4.00',
        'deferred_capital_goods_input_tax_current' => '5.00',
        'input_tax_vat_exempt_sales' => '6.00',
        'vat_refund_tcc_claimed' => '7.00',
        'input_vat_unpaid_payables' => '8.00',
        'other_input_tax_deductions' => '9.00',
        'input_vat_settled_unpaid_payables' => '2.00',
    ]);

    expect($items['item_31a_taxable_sales'])->toBe('1000.00')
        ->and($items['item_31b_output_vat'])->toBe('120.00')
        ->and($items['item_34a_total_sales'])->toBe('1500.00')
        ->and($items['item_34b_output_tax'])->toBe('120.00')
        ->and($items['item_37b_total_adjusted_output_tax'])->toBe('105.00')
        ->and($items['item_43b_total_prior_input_tax'])->toBe('20.00')
        ->and($items['item_50a_total_current_purchases'])->toBe('500.00')
        ->and($items['item_50b_total_current_input_tax'])->toBe('60.00')
        ->and($items['item_51b_total_available_input_tax'])->toBe('80.00')
        ->and($items['item_57b_total_input_tax_deductions'])->toBe('35.00')
        ->and($items['item_59b_adjusted_input_tax_deductions'])->toBe('37.00')
        ->and($items['item_60b_total_allowable_input_tax'])->toBe('43.00')
        ->and($items['item_61_net_vat_payable'])->toBe('62.00')
        ->and($items['item_61_is_excess_input_tax'])->toBeFalse()
        ->and($items['memo_total_sales_inclusive'])->toBe('2020.00')
        ->and($items['memo_non_vat_sales'])->toBe('400.00');
});

test('BIR 2550Q computation reports a negative item 61 as excess input tax', function () {
    $items = (new BirVatReturnComputation)->compute([
        'taxable_sales' => '50.00',
        'output_vat' => '6.00',
        'input_vat_reworking_cost' => '100.00',
        'input_vat' => '100.00',
    ]);

    expect($items['item_60b_total_allowable_input_tax'])->toBe('100.00')
        ->and($items['item_61_net_vat_payable'])->toBe('-94.00')
        ->and($items['item_61_is_excess_input_tax'])->toBeTrue();
});
