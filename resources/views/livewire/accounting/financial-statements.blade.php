<section class="financial-statements-page" aria-labelledby="financial-statements-heading">
    <header class="page-heading financial-statements-heading">
        <div>
            <h1 id="financial-statements-heading">Financial Statements</h1>
            <p class="page-subtitle">Select a statement and reporting dates to view the demonstration document.</p>
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

    <article id="financial-statement-document" class="financial-statement-document" aria-label="Selected financial statement demonstration">
        <header class="financial-statement-document-heading">
            <p class="financial-statement-eyebrow">Accounting report demonstration</p>
            <h2>{{ $statementTitle }}</h2>
            <p>From: {{ $fromDate !== '' ? $fromDate : 'Not selected' }}</p>
            <p>To: {{ $toDate !== '' ? $toDate : 'Not selected' }}</p>
        </header>

        <div class="financial-statement-empty" role="status">
            <p>No financial statement is available.</p>
            <span>This is not a real financial statement. Accounting mappings, policies, and calculations are not implemented; demo journals and POS sales are not included.</span>
        </div>
    </article>
</section>
