<section class="financial-statements-page" aria-labelledby="financial-statements-heading">
    <header class="page-heading financial-statements-heading">
        <div>
            <h1 id="financial-statements-heading">Financial Statements</h1>
            <p class="page-subtitle">Generate an Income Statement from approved FCDC accounting activity.</p>
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
            <p>Inclusive Manila period: {{ $fromDate !== '' ? $fromDate : 'Not selected' }} through {{ $toDate !== '' ? $toDate : 'Not selected' }}</p>
            <p>Generated: {{ $generatedAt }}</p>
            <p>Scope: approved FCDC posted accounting activity; historical detail is not inferred.</p>
        </header>

        @if ($statementType !== 'income-statement')
            <div class="financial-statement-empty" role="status">
                <p>Balance Sheet is unavailable.</p>
                <span>This page currently generates the Income Statement only.</span>
            </div>
        @elseif (! $report['available'])
            <div class="financial-statement-empty" role="status">
                <p>Income Statement unavailable.</p>
                <span>{{ $report['error'] }}</span>
            </div>
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
