@php($money = fn ($amount) => '₱'.number_format((float) $amount, 2))
@php($manilaDate = fn ($date) => $date->copy()->timezone('Asia/Manila'))
@php($item61 = (float) $items['item_61_net_vat_payable'])
<section class="vat-page vat-return-page" aria-labelledby="vat-return-heading">
    <header class="vat-page-heading">
        <div>
            <h1 id="vat-return-heading">VAT Return Preparation</h1>
            <p class="page-subtitle">BIR Form 2550Q preparation worksheet for review. POS output VAT and Reworking Cost Record input VAT are read from saved system records.</p>
        </div>
        <span class="vat-rate-badge">BIR Form 2550Q preparation</span>
    </header>

    <p class="vat-prep-only-banner" role="note"><strong>For preparation only.</strong> This quarterly worksheet supports BIR Form 2550Q preparation and does not submit the return to the BIR. It is not an official Form 2550Q, not a complete official return and not a submitted filing.</p>

    <div class="vat-return-controls vat-return-screen-only">
        <div class="vat-return-period-controls">
            <label>
                <span>Year</span>
                <select wire:model.live="selectedYear">
                    @foreach ($years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Calendar quarter</span>
                <select wire:model.live="selectedQuarter">
                    @foreach ($quarters as $number => $label)
                        <option value="{{ $number }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="vat-return-actions">
            <button class="vat-detail-button" type="button" wire:click="saveAdjustments">Save adjustments</button>
            <button class="vat-detail-button" type="button" wire:click="exportCsv">Export CSV</button>
            <button class="vat-detail-button" type="button" onclick="document.body.classList.add('printing-vat-return'); window.print(); setTimeout(() => document.body.classList.remove('printing-vat-return'), 100)">Export PDF (Save as PDF)</button>
        </div>
    </div>

    <div class="vat-return-save-state vat-return-screen-only" aria-live="polite">
        @if ($statusMessage !== '')
            <p class="vat-return-save-message" role="status">{{ $statusMessage }}</p>
        @elseif ($adjustmentsSavedAt)
            <p class="vat-return-save-message">Manual adjustments last saved {{ $adjustmentsSavedAt->format('Y-m-d H:i') }} (Asia/Manila)@if ($adjustmentsSavedBy) by {{ $adjustmentsSavedBy }}@endif.</p>
        @else
            <p class="vat-return-save-message">No manual adjustments saved for {{ $periodLabel }} yet. Enter the applicable 2550Q adjustment items below, then save.</p>
        @endif
        @if ($errors->any())
            <p class="vat-return-save-error" role="alert">Some manual adjustment values need correction. Amounts must be zero or greater with up to two decimal places.</p>
        @endif
    </div>

    <article id="vat-return-print" class="vat-return-document" aria-label="BIR Form 2550Q VAT preparation worksheet">
        <header class="vat-return-document-heading">
            <p class="vat-return-document-eyebrow">BIR Form 2550Q · Quarterly VAT Return preparation worksheet</p>
            <h2>Fabellion Construction and Development Corp.</h2>
            <p>Quarterly VAT preparation worksheet · {{ $periodLabel }}</p>
            <p>Generated {{ $generatedAt->format('Y-m-d H:i:s P') }} · Reporting timezone: Asia/Manila</p>
        </header>

        <p class="vat-return-disclaimer">Preparation aid only. This is not an official Form 2550Q, not a complete official return and not a submitted filing. It is not the company’s final VAT liability and does not submit anything to the BIR.</p>

        <section aria-labelledby="vat-return-period-heading">
            <h3 id="vat-return-period-heading">Part I — Taxpayer and return period</h3>
            <div class="vat-table-wrap">
                <table>
                    <thead><tr><th>Worksheet item</th><th>Details</th></tr></thead>
                    <tbody>
                        <tr><td>Taxpayer</td><td>Fabellion Construction and Development Corp.</td></tr>
                        <tr><td>Return being prepared</td><td>BIR Form 2550Q — Quarterly VAT Return (preparation worksheet)</td></tr>
                        <tr><td>Period covered</td><td>{{ $periodLabel }}</td></tr>
                        <tr><td>Reporting timezone</td><td>Asia/Manila</td></tr>
                        <tr><td>Scope</td><td>Completed POS sales, saved Reworking Cost Record input VAT, and manual 2550Q adjustments</td></tr>
                        <tr><td>Filing status</td><td><strong>Preparation only — not submitted to the BIR</strong></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="vat-return-sales-heading">
            <h3 id="vat-return-sales-heading">Part II — Sales and output VAT (Items 31–37)</h3>
            <div class="vat-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Description</th>
                            <th class="numeric">A. Sales (exclusive of VAT)</th>
                            <th class="numeric">B. Output tax</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>31</td>
                            <td>VATable sales (Taxable sales) <span class="vat-return-source">POS VAT records</span></td>
                            <td class="numeric">31A · {{ $money($items['item_31a_taxable_sales']) }}</td>
                            <td class="numeric">31B · {{ $money($items['item_31b_output_vat']) }}</td>
                        </tr>
                        <tr>
                            <td>32</td>
                            <td>Zero-rated sales <span class="vat-return-source">POS VAT records</span></td>
                            <td class="numeric">{{ $money($items['item_32a_zero_rated_sales']) }}</td>
                            <td class="numeric">—</td>
                        </tr>
                        <tr>
                            <td>33</td>
                            <td>VAT-exempt sales <span class="vat-return-source">POS VAT records</span></td>
                            <td class="numeric">{{ $money($items['item_33a_exempt_sales']) }}</td>
                            <td class="numeric">—</td>
                        </tr>
                        <tr class="vat-return-total-row">
                            <td>34</td>
                            <td><strong>Total Sales and Output Tax Due</strong><br><span class="vat-return-item-note">Sum of Items 31A to 33A / Item 31B</span></td>
                            <td class="numeric"><strong>34A · {{ $money($items['item_34a_total_sales']) }}</strong></td>
                            <td class="numeric"><strong>34B · {{ $money($items['item_34b_output_tax']) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="vat-return-note">Memo: Non-VAT / outside scope sales of {{ $money($items['memo_non_vat_sales']) }} are excluded from the 2550Q sales lines. VAT-inclusive total of {{ $money($items['memo_total_sales_inclusive']) }} is shown for POS reconciliation only.</p>

            <div class="vat-return-adjustment-panel">
                <h4>Output tax adjustments (Items 35–37)</h4>
                <div class="vat-return-adjustment-grid">
                    <label class="vat-return-field">
                        <span>Item 35B — Less: Output VAT on uncollected receivables</span>
                        <input class="vat-return-amount-input @error('outputVatUncollectedReceivables') is-invalid @enderror" type="text" inputmode="decimal" wire:model.live.debounce.400ms="outputVatUncollectedReceivables" aria-label="Item 35B output VAT on uncollected receivables">
                        <span class="vat-return-print-value">{{ $money($items['item_35b_output_vat_uncollected_receivables']) }}</span>
                        <small>Manual adjustment. Enter 0.00 when not applicable.</small>
                    </label>
                    <label class="vat-return-field">
                        <span>Item 36B — Add: Output VAT on recovered uncollected receivables previously deducted</span>
                        <input class="vat-return-amount-input @error('outputVatRecoveredReceivables') is-invalid @enderror" type="text" inputmode="decimal" wire:model.live.debounce.400ms="outputVatRecoveredReceivables" aria-label="Item 36B output VAT on recovered uncollected receivables">
                        <span class="vat-return-print-value">{{ $money($items['item_36b_output_vat_recovered_receivables']) }}</span>
                        <small>Manual adjustment. Enter 0.00 when not applicable.</small>
                    </label>
                </div>
                <div class="vat-return-computed-line">
                    <span>Item 37 — Total Adjusted Output Tax Due <small>(Item 34B less Item 35B add Item 36B)</small></span>
                    <strong>{{ $money($items['item_37b_total_adjusted_output_tax']) }}</strong>
                </div>
            </div>
        </section>

        <section aria-labelledby="vat-return-input-heading">
            <h3 id="vat-return-input-heading">Part III — Input VAT (Items 38–60)</h3>

            <h4 class="vat-return-subheading">Input tax carried over and other input tax (Items 38–43)</h4>
            <div class="vat-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Description</th>
                            <th class="numeric">Input tax</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>38</td>
                            <td>Input Tax Carried Over from Previous Quarter</td>
                            <td class="numeric"><input class="vat-return-amount-input @error('inputTaxCarriedOver') is-invalid @enderror" type="text" inputmode="decimal" wire:model.live.debounce.400ms="inputTaxCarriedOver" aria-label="Item 38B input tax carried over from previous quarter"><span class="vat-return-print-value">{{ $money($items['item_38b_input_tax_carried_over']) }}</span></td>
                        </tr>
                        <tr>
                            <td>39</td>
                            <td>Input Tax Deferred on Capital Goods Exceeding ₱1 Million from Previous Quarter</td>
                            <td class="numeric"><input class="vat-return-amount-input @error('deferredCapitalGoodsInputTaxPrior') is-invalid @enderror" type="text" inputmode="decimal" wire:model.live.debounce.400ms="deferredCapitalGoodsInputTaxPrior" aria-label="Item 39B deferred input tax on capital goods from previous quarter"><span class="vat-return-print-value">{{ $money($items['item_39b_deferred_capital_goods_input_tax_prior']) }}</span></td>
                        </tr>
                        <tr>
                            <td>40</td>
                            <td>Transitional Input Tax</td>
                            <td class="numeric"><input class="vat-return-amount-input @error('transitionalInputTax') is-invalid @enderror" type="text" inputmode="decimal" wire:model.live.debounce.400ms="transitionalInputTax" aria-label="Item 40B transitional input tax"><span class="vat-return-print-value">{{ $money($items['item_40b_transitional_input_tax']) }}</span></td>
                        </tr>
                        <tr>
                            <td>41</td>
                            <td>Presumptive Input Tax</td>
                            <td class="numeric"><input class="vat-return-amount-input @error('presumptiveInputTax') is-invalid @enderror" type="text" inputmode="decimal" wire:model.live.debounce.400ms="presumptiveInputTax" aria-label="Item 41B presumptive input tax"><span class="vat-return-print-value">{{ $money($items['item_41b_presumptive_input_tax']) }}</span></td>
                        </tr>
                        <tr>
                            <td>42</td>
                            <td>
                                Others (Specify)
                                <input class="vat-return-text-input @error('otherInputTaxDescription') is-invalid @enderror" type="text" wire:model.live.debounce.400ms="otherInputTaxDescription" maxlength="255" placeholder="Description of other input tax" aria-label="Item 42B other input tax description">
                                <span class="vat-return-print-value vat-return-print-description">{{ $otherInputTaxDescription ?: '—' }}</span>
                                <span class="vat-return-item-note">Use this for input tax not captured by the Reworking Cost Records or other manual tax information.</span>
                            </td>
                            <td class="numeric"><input class="vat-return-amount-input @error('otherInputTax') is-invalid @enderror" type="text" inputmode="decimal" wire:model.live.debounce.400ms="otherInputTax" aria-label="Item 42B other input tax"><span class="vat-return-print-value">{{ $money($items['item_42b_other_input_tax']) }}</span></td>
                        </tr>
                        <tr class="vat-return-total-row">
                            <td>43</td>
                            <td><strong>Total</strong> <span class="vat-return-item-note">(Sum of Items 38B to 42B)</span></td>
                            <td class="numeric"><strong>{{ $money($items['item_43b_total_prior_input_tax']) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h4 class="vat-return-subheading">Current purchases and input tax (Item 50)</h4>
            <div class="vat-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Description</th>
                            <th class="numeric">A. Purchases</th>
                            <th class="numeric">B. Input tax</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>50A</td>
                            <td>Reworking cost total <span class="vat-return-source">Inventory · Reworking Cost Records</span></td>
                            <td class="numeric">50A · {{ $money($items['item_50a_total_current_purchases']) }}</td>
                            <td class="numeric">—</td>
                        </tr>
                        <tr>
                            <td>50B</td>
                            <td>Input VAT on reworking cost records <span class="vat-return-source">Inventory · Reworking Cost Records</span></td>
                            <td class="numeric">—</td>
                            <td class="numeric">50B · {{ $money($items['item_50b_total_current_input_tax']) }}</td>
                        </tr>
                        <tr class="vat-return-total-row">
                            <td>50</td>
                            <td><strong>Total Current Purchases and Input Tax</strong><br><span class="vat-return-item-note">Read-only totals from saved Reworking Cost Records for this quarter</span></td>
                            <td class="numeric"><strong>{{ $money($items['item_50a_total_current_purchases']) }}</strong></td>
                            <td class="numeric"><strong>{{ $money($items['item_50b_total_current_input_tax']) }}</strong></td>
                        </tr>
                        <tr class="vat-return-total-row">
                            <td>51</td>
                            <td><strong>Total Available Input Tax</strong><br><span class="vat-return-item-note">Item 43B plus Item 50B</span></td>
                            <td class="numeric">—</td>
                            <td class="numeric"><strong>{{ $money($items['item_51b_total_available_input_tax']) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @if ($summary['input_vat_record_count'] > 0)
                <p class="vat-return-note">Input VAT of {{ $money($summary['input_vat']) }} is copied from {{ $summary['input_vat_record_count'] }} saved Reworking Cost Record(s) for {{ $summary['input_vat_batch_count'] }} batch(es) dated within this quarter. It does not mean every company input VAT is captured.</p>
            @else
                <p class="vat-return-note">Input VAT not captured from Reworking Cost Records for this quarter; Item 50B is {{ $money($items['item_50b_total_current_input_tax']) }}. Use Items 38–42 for applicable prior, transitional, presumptive, or other input tax.</p>
            @endif

            <h4 class="vat-return-subheading">Deductions from input tax (Items 52–60)</h4>
            <div class="vat-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Description</th>
                            <th class="numeric">Input tax</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>52</td>
                            <td>Input Tax on Purchases/Importation of Capital Goods Exceeding ₱1 Million Deferred for the Succeeding Period</td>
                            <td class="numeric"><input class="vat-return-amount-input @error('deferredCapitalGoodsInputTaxCurrent') is-invalid @enderror" type="text" inputmode="decimal" wire:model.live.debounce.400ms="deferredCapitalGoodsInputTaxCurrent" aria-label="Item 52B deferred input tax on capital goods for succeeding period"><span class="vat-return-print-value">{{ $money($items['item_52b_deferred_capital_goods_input_tax_current']) }}</span></td>
                        </tr>
                        <tr>
                            <td>53</td>
                            <td>Input Tax Attributable to VAT-Exempt Sales</td>
                            <td class="numeric"><input class="vat-return-amount-input @error('inputTaxVatExemptSales') is-invalid @enderror" type="text" inputmode="decimal" wire:model.live.debounce.400ms="inputTaxVatExemptSales" aria-label="Item 53B input tax attributable to VAT-exempt sales"><span class="vat-return-print-value">{{ $money($items['item_53b_input_tax_vat_exempt_sales']) }}</span></td>
                        </tr>
                        <tr>
                            <td>54</td>
                            <td>VAT Refund/TCC Claimed</td>
                            <td class="numeric"><input class="vat-return-amount-input @error('vatRefundTccClaimed') is-invalid @enderror" type="text" inputmode="decimal" wire:model.live.debounce.400ms="vatRefundTccClaimed" aria-label="Item 54B VAT refund or TCC claimed"><span class="vat-return-print-value">{{ $money($items['item_54b_vat_refund_tcc_claimed']) }}</span></td>
                        </tr>
                        <tr>
                            <td>55</td>
                            <td>Input VAT on Unpaid Payables</td>
                            <td class="numeric"><input class="vat-return-amount-input @error('inputVatUnpaidPayables') is-invalid @enderror" type="text" inputmode="decimal" wire:model.live.debounce.400ms="inputVatUnpaidPayables" aria-label="Item 55B input VAT on unpaid payables"><span class="vat-return-print-value">{{ $money($items['item_55b_input_vat_unpaid_payables']) }}</span></td>
                        </tr>
                        <tr>
                            <td>56</td>
                            <td>
                                Others (Specify)
                                <input class="vat-return-text-input @error('otherInputTaxDeductionsDescription') is-invalid @enderror" type="text" wire:model.live.debounce.400ms="otherInputTaxDeductionsDescription" maxlength="255" placeholder="Description of other deductions" aria-label="Item 56B other deductions description">
                                <span class="vat-return-print-value vat-return-print-description">{{ $otherInputTaxDeductionsDescription ?: '—' }}</span>
                            </td>
                            <td class="numeric"><input class="vat-return-amount-input @error('otherInputTaxDeductions') is-invalid @enderror" type="text" inputmode="decimal" wire:model.live.debounce.400ms="otherInputTaxDeductions" aria-label="Item 56B other deductions from input tax"><span class="vat-return-print-value">{{ $money($items['item_56b_other_input_tax_deductions']) }}</span></td>
                        </tr>
                        <tr class="vat-return-total-row">
                            <td>57</td>
                            <td><strong>Total Deductions from Input Tax</strong> <span class="vat-return-item-note">(Sum of Items 52B to 56B)</span></td>
                            <td class="numeric"><strong>{{ $money($items['item_57b_total_input_tax_deductions']) }}</strong></td>
                        </tr>
                        <tr>
                            <td>58</td>
                            <td>Add: Input VAT on Settled Unpaid Payables Previously Deducted</td>
                            <td class="numeric"><input class="vat-return-amount-input @error('inputVatSettledUnpaidPayables') is-invalid @enderror" type="text" inputmode="decimal" wire:model.live.debounce.400ms="inputVatSettledUnpaidPayables" aria-label="Item 58B input VAT on settled unpaid payables previously deducted"><span class="vat-return-print-value">{{ $money($items['item_58b_input_vat_settled_unpaid_payables']) }}</span></td>
                        </tr>
                        <tr class="vat-return-total-row">
                            <td>59</td>
                            <td><strong>Adjusted Deductions from Input Tax (Deductions applied)</strong> <span class="vat-return-item-note">(Item 57B plus Item 58B)</span></td>
                            <td class="numeric"><strong>{{ $money($items['item_59b_adjusted_input_tax_deductions']) }}</strong></td>
                        </tr>
                        <tr class="vat-return-total-row">
                            <td>60</td>
                            <td><strong>Total Allowable Input Tax</strong><br><span class="vat-return-item-note">Item 51B less Item 59B</span></td>
                            <td class="numeric"><strong>{{ $money($items['item_60b_total_allowable_input_tax']) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="vat-return-payable-heading">
            <h3 id="vat-return-payable-heading">Part IV — Net VAT payable (Item 61)</h3>
            <div class="vat-table-wrap">
                <table>
                    <thead><tr><th>Item</th><th>Description</th><th class="numeric">Amount</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>61</td>
                            <td>
                                <strong>{{ $items['item_61_is_excess_input_tax'] ? 'Excess Input Tax' : 'Net VAT Payable' }}</strong>
                                <span class="vat-return-item-note">Net VAT Payable/(Excess Input Tax) · Item 37B less Item 60B</span>
                            </td>
                            <td class="numeric {{ $items['item_61_is_excess_input_tax'] ? 'vat-return-excess' : '' }}">
                                <strong>{{ $money(abs($item61)) }}</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="vat-return-note">The POS-only VAT payable estimate above uses recorded output VAT, Item 51 available input tax, and the manual deductions for this quarter. Item 61 is the amount carried to Part II, Item 15 for review. It is not a determination of final VAT liability.</p>
            <p class="vat-return-note vat-return-screen-only">Need to record input tax that is not in the system? Use Item 42 for other available input tax, or the applicable deduction items 52–58, and save the adjustments. This worksheet does not submit the return to the BIR.</p>
            @if ($summary['record_count'] === 0)
                <p class="temporary-notice" role="status">No recorded POS sales for {{ $periodLabel }}. Sales and output tax items are PHP 0.00.</p>
            @endif
        </section>

        <div class="vat-return-manual-notes">
            <label class="vat-return-field">
                <span>Preparer notes / manual tax information</span>
                <textarea wire:model.live.debounce.500ms="notes" rows="3" maxlength="2000" placeholder="Supporting notes for manual adjustments or missing tax information">{{ $notes }}</textarea>
                <span class="vat-return-print-value vat-return-print-notes">{{ $notes ?: '—' }}</span>
            </label>
        </div>

        <div class="vat-return-document-actions vat-return-screen-only">
            <button class="vat-detail-button" type="button" wire:click="saveAdjustments">Save adjustments for {{ $periodLabel }}</button>
        </div>
    </article>

    <section class="vat-record-card vat-return-records vat-return-screen-only" aria-labelledby="vat-return-records-heading">
        <header>
            <div>
                <h2 id="vat-return-records-heading">{{ $periodLabel }} supporting transactions</h2>
                <p>Saved completed POS sales for this calendar quarter. Sales and output VAT source records are read-only.</p>
            </div>
            <span class="vat-record-count">{{ $records->count() }} recorded sales</span>
        </header>
        <div class="vat-table-wrap">
            <table>
                <thead><tr><th>Transaction no.</th><th>Manila completion</th><th>Tax classification</th><th class="numeric">Taxable sales</th><th class="numeric">Output VAT</th><th class="numeric">VAT-inclusive total</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr wire:key="vat-preparation-record-{{ $record->id }}">
                            <td><strong>{{ $record->posTransaction->transaction_number }}</strong></td>
                            <td>{{ $manilaDate($record->completed_at)->format('Y-m-d H:i') }}</td>
                            <td>{{ \App\Support\TaxClassification::label($record->tax_classification) }}</td>
                            <td class="numeric">{{ $money($record->taxable_sales) }}</td>
                            <td class="numeric">{{ $money($record->output_vat) }}</td>
                            <td class="numeric">{{ $money($record->total) }}</td>
                            <td><button class="vat-detail-button" type="button" wire:click="viewRecord({{ $record->id }})">View details</button></td>
                        </tr>
                    @empty
                        <tr><td class="vat-empty" colspan="7">No recorded POS sales.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="vat-record-card vat-return-records vat-return-screen-only" aria-labelledby="vat-return-input-records-heading">
        <header>
            <div>
                <h2 id="vat-return-input-records-heading">{{ $periodLabel }} supporting input VAT records</h2>
                <p>Saved Reworking Cost Records for this calendar quarter. Item 50 source records are read-only.</p>
            </div>
            <span class="vat-record-count">{{ $summary['input_vat_record_count'] }} input VAT records</span>
        </header>
        <div class="vat-table-wrap">
            <table>
                <thead><tr><th>Batch number</th><th>Project</th><th>Record date (Asia/Manila)</th><th>Description</th><th class="numeric">Reworking cost</th><th class="numeric">Input VAT</th></tr></thead>
                <tbody>
                    @forelse ($inputVatRecords as $record)
                        <tr wire:key="vat-preparation-input-{{ $record['source_key'] }}">
                            <td><strong>{{ $record['batch_number'] }}</strong></td>
                            <td>{{ $record['project'] }}</td>
                            <td>{{ $record['date'] }}</td>
                            <td>{{ $record['description'] }}</td>
                            <td class="numeric">{{ $money($record['total_reworking_cost']) }}</td>
                            <td class="numeric"><strong>{{ $money($record['input_vat']) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td class="vat-empty" colspan="6">No recorded input VAT from reworking cost records for this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4">Reworking cost total / Input VAT total</th>
                        <th class="numeric">{{ $money($summary['input_vat_reworking_cost']) }}</th>
                        <th class="numeric">{{ $money($summary['input_vat']) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    @if ($selectedRecord)
        @php($sale = $selectedRecord->posTransaction)
        <div class="vat-modal-backdrop vat-return-screen-only" role="presentation" wire:click.self="closeDetails">
            <section class="vat-detail-modal" role="dialog" aria-modal="true" aria-labelledby="vat-preparation-detail-heading">
                <header>
                    <h2 id="vat-preparation-detail-heading">Saved sale details · {{ $sale->transaction_number }}</h2>
                    <button class="vat-modal-close" type="button" wire:click="closeDetails" aria-label="Close details">&times;</button>
                </header>
                <p class="vat-modal-note">{{ $sale->customer_name ?: 'Walk-in customer' }} · {{ $manilaDate($selectedRecord->completed_at)->format('F j, Y g:i A') }} (Asia/Manila)</p>
                <dl>
                    <div><dt>Tax classification</dt><dd>{{ \App\Support\TaxClassification::label($selectedRecord->tax_classification) }}</dd></div>
                    <div><dt>VATable sales</dt><dd>{{ $money($selectedRecord->taxable_sales) }}</dd></div>
                    <div><dt>Zero-rated sales</dt><dd>{{ $money($selectedRecord->zero_rated_sales) }}</dd></div>
                    <div><dt>VAT-exempt sales</dt><dd>{{ $money($selectedRecord->exempt_sales) }}</dd></div>
                    <div><dt>Non-VAT / outside scope</dt><dd>{{ $money($selectedRecord->non_vat_sales) }}</dd></div>
                    <div><dt>Recorded output VAT</dt><dd>{{ $money($selectedRecord->output_vat) }}</dd></div>
                    <div><dt>VAT-inclusive total</dt><dd>{{ $money($selectedRecord->total) }}</dd></div>
                </dl>
                <h3>Saved sale items</h3>
                <div class="vat-table-wrap">
                    <table>
                        <thead><tr><th>Item</th><th>Tax classification</th><th>Quantity</th><th class="numeric">Selling price</th><th class="numeric">Line taxable amount</th></tr></thead>
                        <tbody>
                            @foreach ($sale->lines as $line)
                                <tr wire:key="vat-preparation-line-{{ $line->id }}">
                                    <td>{{ $line->item_name }}</td>
                                    <td>{{ \App\Support\TaxClassification::label($line->tax_classification) }}</td>
                                    <td>{{ number_format((float) $line->quantity, 2) }} {{ $line->unit }}</td>
                                    <td class="numeric">{{ $money($line->selling_price) }}</td>
                                    <td class="numeric">{{ $money($line->line_subtotal) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <footer><button class="vat-detail-button" type="button" wire:click="closeDetails">Close</button></footer>
            </section>
        </div>
    @endif
</section>
