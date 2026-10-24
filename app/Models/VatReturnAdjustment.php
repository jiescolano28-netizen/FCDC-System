<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VatReturnAdjustment extends Model
{
    protected $fillable = [
        'year',
        'quarter',
        'output_vat_uncollected_receivables',
        'output_vat_recovered_receivables',
        'input_tax_carried_over',
        'deferred_capital_goods_input_tax_prior',
        'transitional_input_tax',
        'presumptive_input_tax',
        'other_input_tax',
        'other_input_tax_description',
        'deferred_capital_goods_input_tax_current',
        'input_tax_vat_exempt_sales',
        'vat_refund_tcc_claimed',
        'input_vat_unpaid_payables',
        'other_input_tax_deductions',
        'other_input_tax_deductions_description',
        'input_vat_settled_unpaid_payables',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'quarter' => 'integer',
        'output_vat_uncollected_receivables' => 'decimal:2',
        'output_vat_recovered_receivables' => 'decimal:2',
        'input_tax_carried_over' => 'decimal:2',
        'deferred_capital_goods_input_tax_prior' => 'decimal:2',
        'transitional_input_tax' => 'decimal:2',
        'presumptive_input_tax' => 'decimal:2',
        'other_input_tax' => 'decimal:2',
        'deferred_capital_goods_input_tax_current' => 'decimal:2',
        'input_tax_vat_exempt_sales' => 'decimal:2',
        'vat_refund_tcc_claimed' => 'decimal:2',
        'input_vat_unpaid_payables' => 'decimal:2',
        'other_input_tax_deductions' => 'decimal:2',
        'input_vat_settled_unpaid_payables' => 'decimal:2',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'updated_by');
    }
}
