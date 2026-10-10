<section class="financial-statements-page" aria-labelledby="financial-statements-heading">
    <header class="page-heading financial-statements-heading">
        <div>
            <h1 id="financial-statements-heading">Financial Statements</h1>
            <p class="page-subtitle">Generate an Income Statement or dated corporate Balance Sheet from approved FCDC accounting activity.</p>
        </div>
        <button class="vat-detail-button" type="button" onclick="window.print()">Print statement</button>
    </header>

    <div class="financial-statements-controls" role="group" aria-label="Financial statement selectors">
        <label>
            <span>Statement type</span>
            <select wire:model.live="statementType">
                <option value="income-statement">Income Statement</option>
                <option value="balance-sheet">Balance Sheet</option>
            </select>
        </label>
        <label>
            <span>From date</span>
            <input type="date" wire:model.live="fromDate">
        </label>
        <label>
            <span>To date</span>
            <input type="date" wire:model.live="toDate">
        </label>
    </div>

    <article id="financial-statement-document" class="financial-statement-document" aria-label="Selected financial statement">
        <header class="financial-statement-document-heading">
            <p class="financial-statement-eyebrow">FCDC accounting report · PHP</p>
            <h2>{{ $statementTitle }}</h2>
            @if ($statementType === 'balance-sheet')
                <p>As of {{ isset($report['asOf']) ? \Illuminate\Support\Carbon::parse($report['asOf'])->format('F j, Y') : ($toDate !== '' ? $toDate : 'Not selected') }}</p>
            @else
                <p>Inclusive Manila period: {{ $fromDate !== '' ? $fromDate : 'Not selected' }} through {{ $toDate !== '' ? $toDate : 'Not selected' }}</p>
            @endif
            <p>Generated: {{ $generatedAt }}</p>
            <p>Scope: approved FCDC posted accounting activity; historical detail is not inferred. This report is not audited, filed or legally certified.</p>
        </header>

        @if (! $report['available'])
            <div class="financial-statement-empty" role="status">
                <p>{{ $statementType === 'balance-sheet' ? 'Balance Sheet unavailable.' : 'Income Statement unavailable.' }}</p>
                <span>{{ $report['error'] }}</span>
            </div>
        @elseif ($statementType === 'balance-sheet')
            <p class="financial-statement-coverage" role="note">Assets, liabilities and equity use cumulative posted ledger balances through the selected date. Input and Output VAT reflect approved ledger accounts and authorized postings only; no POS-only VAT estimate or legal registration/deductibility inference is used. Pre-cutover current-year earnings use the approved YTD summary when applicable.</p>
            @php($money = fn (int $cents): string => ($cents < 0 ? '−' : '').'PHP '.number_format(abs($cents) / 100, 2))
            <table class="financial-statement-table">
                <tbody>
                    @foreach (['Asset' => 'Assets', 'Liability' => 'Liabilities', 'Equity' => 'Equity'] as $type => $heading)
                        <tr class="financial-statement-total"><th colspan="2" scope="colgroup">{{ $heading }}</th></tr>
                        @foreach ($report['sections'][$type] as $row)
                            <tr>
                                <th scope="row">{{ $row['label'] }}@if ($row['account']) — {{ $row['account'] }}@endif</th>
                                <td>{{ $money($row['amount']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="financial-statement-total"><th scope="row">Total {{ $heading }}</th><td>{{ $money($report[['Asset' => 'assets', 'Liability' => 'liabilities', 'Equity' => 'equity'][$type]]) }}</td></tr>
                    @endforeach
                    <tr class="financial-statement-total"><th scope="row">Total Liabilities and Equity</th><td>{{ $money($report['liabilitiesAndEquity']) }}</td></tr>
                </tbody>
            </table>
            @if ($report['balanced'])
                <p class="financial-statement-no-activity" role="status">Accounting equation balances: Assets = Liabilities + Equity.</p>
            @else
                <p class="financial-statement-empty" role="alert">Accounting error: Assets do not equal Liabilities plus Equity. Difference: {{ $money($report['assets'] - $report['liabilitiesAndEquity']) }}. No balancing equity amount has been invented.</p>
            @endif
        @else
            @if ($report['summary'])
                <p class="financial-statement-coverage" role="note">Approved pre-cutover YTD summary included. Historical summary through {{ \Illuminate\Support\Carbon::parse($report['summary']->through_date)->format('F j, Y') }}; transaction-level coverage begins {{ \Illuminate\Support\Carbon::parse($report['cutover'])->format('F j, Y') }}.</p>
            @endif
            @if (! $report['hasActivity'])
                <p class="financial-statement-no-activity" role="status">No posted income or expense activity in this period.</p>
            @endif
            @php($money = fn (int $cents): string => ($cents < 0 ? '−' : '').'PHP '.number_format(abs($cents) / 100, 2))
            <table class="financial-statement-table">
                <tbody>
                    <tr><th scope="row">Sales</th><td>{{ $money($report['sales']) }}</td></tr>
                    <tr><th scope="row">Cost of Goods Sold</th><td>({{ $money($report['cogs']) }})</td></tr>
                    <tr class="financial-statement-total"><th scope="row">Gross Profit</th><td>{{ $money($report['grossProfit']) }}</td></tr>
                    <tr><th scope="row">Operating expenses</th><td>({{ $money($report['operating']) }})</td></tr>
                    <tr><th scope="row">Other income</th><td>{{ $money($report['otherIncome']) }}</td></tr>
                    <tr><th scope="row">Other expenses</th><td>({{ $money($report['otherExpense']) }})</td></tr>
                    <tr class="financial-statement-total"><th scope="row">{{ $report['netIncome'] < 0 ? 'Net Loss' : 'Net Income' }}</th><td>{{ $money($report['netIncome']) }}</td></tr>
                </tbody>
            </table>
        @endif
    </article>
</section>
