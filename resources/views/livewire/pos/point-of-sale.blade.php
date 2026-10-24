@php($money = fn ($amount) => '₱'.number_format((float) $amount, 2))
<section class="pos-page" aria-labelledby="pos-heading">
    <header class="pos-heading">
        <div>
            <h1 id="pos-heading">Point of sale</h1>
            <p class="page-subtitle">Select one tax classification for the whole sale. VAT and totals update automatically.</p>
        </div>
    </header>

    <div class="pos-layout">
        <div class="pos-products">
            <label class="pos-search-label" for="pos-search">Search materials</label>
            <input id="pos-search" class="pos-search" type="search" placeholder="Search materials to sell..." wire:model.live.debounce.300ms="search">
            <label class="pos-search-label" for="pos-category">Category</label>
            <select id="pos-category" class="pos-search" wire:model.live="category">
                <option value="">All categories</option>
                @foreach ($categories as $categoryOption)
                    <option value="{{ $categoryOption }}">{{ $categoryOption }}</option>
                @endforeach
            </select>

            <div class="pos-product-grid">
                @forelse ($items as $item)
                    <article class="pos-product-card" wire:key="pos-item-{{ $item->id }}">
                        @if ($item->image)
                            <button class="pos-image-button" type="button" wire:click="viewImage({{ $item->id }})" aria-label="View {{ $item->name }} image">
                                <img src="{{ asset('storage/'.$item->image) }}" alt="{{ $item->name }}">
                            </button>
                        @endif
                        <div class="pos-product-category">{{ $item->category }}</div>
                        <h2>{{ $item->name }}</h2>
                        <div class="pos-product-footer">
                            <strong>{{ $money($item->selling_price) }}</strong>
                            <span>{{ number_format((float) $item->qty, 2) }} {{ $item->unit }} left</span>
                        </div>
                        @can('pos.checkout')
                            <button class="pos-add-button" type="button" wire:click="addToCart({{ $item->id }})">Add to Sale</button>
                        @endcan
                    </article>
                @empty
                    <p class="pos-empty-products">No matching in-stock materials.</p>
                @endforelse
            </div>
        </div>

        <aside class="pos-cart-card" aria-labelledby="pos-cart-heading">
            <header class="pos-cart-header">
                <div>
                    <p class="pos-cart-eyebrow">Transaction</p>
                    <h2 id="pos-cart-heading">Current Sale</h2>
                </div>
                <span class="pos-cart-count">{{ $lines->count() }} {{ $lines->count() === 1 ? 'item' : 'items' }}</span>
            </header>

            <div class="pos-cart-body">
                <div class="pos-cart-lines">
                    @forelse ($lines as $item)
                        <div class="pos-cart-line" wire:key="cart-line-{{ $item->id }}">
                            <div class="pos-cart-name">
                                {{ $item->name }}<span>{{ $money($item->selling_price) }} / {{ $item->unit }}</span>
                            </div>
                            @can('pos.checkout')
                                <div class="pos-quantity-controls">
                                    <input aria-label="{{ $item->name }} quantity" type="text" inputmode="numeric" pattern="[0-9]*" value="{{ number_format($item->cart_quantity, 0, '.', '') }}" data-previous-value="{{ number_format($item->cart_quantity, 0, '.', '') }}" onfocus="this.dataset.editingValue = this.value; this.dataset.invalidInput = 'false'" onkeydown="if (this.dataset.invalidInput === 'true') { if (['Backspace', 'Delete'].includes(event.key) || (event.ctrlKey && event.key.toLowerCase() === 'a')) this.dataset.invalidInput = 'false'; else if (!['Tab', 'Escape', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) event.preventDefault(); }" oninput="if (/^[0-9]*$/.test(this.value)) this.dataset.previousValue = this.value; else { this.value = this.dataset.editingValue; this.dataset.invalidInput = 'true'; }" wire:change="setQuantity({{ $item->id }}, $event.target.value)">
                                    <button class="pos-remove-button" type="button" wire:click="removeFromCart({{ $item->id }})" aria-label="Remove {{ $item->name }}">×</button>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <p class="pos-cart-empty">Cart is empty. Tap a material to add it.</p>
                    @endforelse
                </div>

                <div class="pos-cart-classification">
                    <div class="pos-cart-classification-heading">
                        <label for="pos-sale-tax-classification">Tax classification</label>
                        <span class="pos-cart-rate-badge">{{ $cartTaxClassification ? \App\Support\TaxClassification::rateLabel($cartTaxClassification) : 'Not set' }}</span>
                    </div>
                    <p>One classification applies to every item in this sale.</p>
                    @can('pos.checkout')
                        <select id="pos-sale-tax-classification" wire:change="setSaleTaxClassification($event.target.value)">
                            <option value="" @selected($cartTaxClassification === null)>Select a classification</option>
                            @foreach ($taxClassifications as $classificationValue => $classificationLabel)
                                <option value="{{ $classificationValue }}" @selected($cartTaxClassification === $classificationValue)>{{ $classificationLabel }}</option>
                            @endforeach
                        </select>
                    @else
                        <strong>{{ $cartTaxClassification ? \App\Support\TaxClassification::label($cartTaxClassification) : '—' }}</strong>
                    @endcan
                    @error('saleTaxClassification') <p class="settings-error">{{ $message }}</p> @enderror
                </div>

                @error('cart') <p class="pos-cart-error">{{ $message }}</p> @enderror

                <div class="pos-cart-totals">
                    <p class="pos-cart-summary-heading">Sale summary</p>
                    <div class="pos-cart-summary-row"><span>Subtotal</span><span>{{ $money($subtotal) }}</span></div>
                    <div class="pos-cart-summary-row pos-cart-vat-row">
                        <span>VAT <span class="pos-cart-vat-badge">{{ $cartTaxClassification ? \App\Support\TaxClassification::rateLabel($cartTaxClassification) : '0%' }}</span></span>
                        <span>{{ $money($vat) }}</span>
                    </div>
                    <strong class="pos-cart-total"><span>Total</span><span>{{ $money($total) }}</span></strong>
                </div>

                @can('pos.checkout')
                    <div class="pos-payment-fields">
                        <label>Customer (optional)<input type="text" maxlength="255" wire:model="customerName"></label>
                        @error('customerName') <p class="settings-error">{{ $message }}</p> @enderror
                        <label>Payment method
                            <select wire:model.live="paymentMethod">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="bank_transfer">Bank transfer</option>
                            </select>
                        </label>
                        <label>Amount received (PHP)<input type="number" min="0" step="0.01" wire:model="amountReceived"></label>
                        @if ($paymentMethod !== 'cash')
                            <label>Reference number<input type="text" maxlength="255" wire:model="paymentReference"></label>
                        @endif
                        @error('amountReceived') <p class="settings-error">{{ $message }}</p> @enderror
                        @error('paymentReference') <p class="settings-error">{{ $message }}</p> @enderror
                        @error('paymentMethod') <p class="settings-error">{{ $message }}</p> @enderror
                        @error('accounting') <p class="settings-error">{{ $message }}</p> @enderror
                        @error('mapping') <p class="settings-error">{{ $message }}</p> @enderror
                        @error('payment_method') <p class="settings-error">{{ $message }}</p> @enderror
                        <button class="pos-checkout-button" type="button" wire:click="checkout" @disabled($lines->isEmpty() || $cartTaxClassification === null)>Complete transaction · {{ $money($total) }}</button>
                    </div>
                @endcan
                @if (session()->has('status'))
                    <p class="pos-confirmation" role="status">{{ session('status') }}</p>
                @endif
            </div>
        </aside>
    </div>

    <section class="pos-sales-history" aria-labelledby="pos-history-heading">
        <h2 id="pos-history-heading">Transaction history</h2>
        <div class="reports-table-wrap">
            <table class="reports-table">
                <thead><tr><th>Transaction</th><th>Date</th><th>Customer</th><th>Items</th><th>Tax classification</th><th class="numeric">Net sales</th><th class="numeric">Output VAT</th><th class="numeric">Total sales</th><th>Payment</th><th>Status</th><th>Receipt</th></tr></thead>
                <tbody>
                    @forelse ($history as $transaction)
                        <tr wire:key="pos-transaction-{{ $transaction->id }}">
                            <td>{{ $transaction->transaction_number }}</td>
                            <td>{{ \App\Support\PhilippineTime::format($transaction->completed_at, 'Y-m-d H:i') }}</td>
                            <td>{{ $transaction->customer_name ?: '—' }}</td>
                            <td>{{ $transaction->lines->map(fn ($line) => $line->item_name.' × '.number_format((float) $line->quantity, 2).' '.$line->unit)->implode(', ') }}</td>
                            <td>{{ \App\Support\TaxClassification::label($transaction->vatRecord?->tax_classification ?? $transaction->tax_classification) }}</td>
                            <td class="numeric">{{ $money($transaction->subtotal) }}</td>
                            <td class="numeric">{{ $money($transaction->vat_amount) }}</td>
                            <td class="numeric">{{ $money($transaction->total) }}</td>
                            <td>{{ str_replace('_', ' ', ucfirst($transaction->payment_method)) }}</td>
                            <td>{{ ucfirst($transaction->status) }}</td>
                            <td><button type="button" wire:click="viewReceipt({{ $transaction->id }})">View receipt</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="reports-empty">No completed transactions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $history->links() }}
    </section>

    @if ($receipt)
        <div class="pos-receipt-overlay" role="dialog" aria-modal="true" aria-labelledby="receipt-heading">
            <article class="pos-receipt" id="pos-receipt-print">
                <h2 id="receipt-heading">Receipt · Completed transaction</h2>
                <header>
                    <strong>{{ $receipt->receipt_company_name ?: 'Fabellion Construction and Development Corp.' }}</strong>
                    @if ($receipt->receipt_company_address)<span>{{ $receipt->receipt_company_address }}</span>@endif
                    @if ($receipt->receipt_company_phone)<span>{{ $receipt->receipt_company_phone }}</span>@endif
                </header>
                <p class="pos-receipt-meta"><span>{{ $receipt->transaction_number }}</span><span>{{ \App\Support\PhilippineTime::format($receipt->completed_at, 'Y-m-d H:i') }} PHT</span></p>
                <p>Customer: {{ $receipt->customer_name ?: '—' }}</p>
                @php($receiptClassification = $receipt->vatRecord?->tax_classification ?? $receipt->tax_classification)
                <p class="pos-receipt-classification">
                    <span>Tax classification</span>
                    <strong>{{ \App\Support\TaxClassification::label($receiptClassification) }}</strong>
                </p>
                <table><tbody>
                    @foreach ($receipt->lines as $line)
                        <tr><td>{{ $line->item_name }}<small>{{ number_format((float) $line->quantity, 2) }} {{ $line->unit }} × {{ $money($line->selling_price) }}</small></td><td>{{ $money($line->line_subtotal) }}</td></tr>
                    @endforeach
                </tbody></table>
                <div class="pos-receipt-totals">
                    <div><span>Subtotal</span><span>{{ $money($receipt->subtotal) }}</span></div>
                    <div><span>VAT rate</span><span>{{ number_format((float) $receipt->vat_rate * 100, 2) }}%</span></div>
                    <div><span>Output VAT</span><span>{{ $money($receipt->vat_amount) }}</span></div>
                    <strong><span>Total</span><span>{{ $money($receipt->total) }}</span></strong>
                    <div><span>{{ str_replace('_', ' ', ucfirst($receipt->payment_method)) }} received</span><span>{{ $money($receipt->amount_received) }}</span></div>
                    <div><span>Change</span><span>{{ $money($receipt->change_due) }}</span></div>
                </div>
                @if ($receipt->payment_reference)<p>Reference: {{ $receipt->payment_reference }}</p>@endif
                <footer class="pos-receipt-actions">
                    <button type="button" wire:click="closeReceipt">Close</button>
                    <button type="button" onclick="document.body.classList.add('printing-pos-receipt'); window.print(); setTimeout(() => document.body.classList.remove('printing-pos-receipt'), 100)">Print receipt</button>
                </footer>
            </article>
        </div>
    @endif

    <dialog class="pos-image-viewer" aria-label="Product image viewer">
        <button class="pos-image-close" type="button" aria-label="Close image">×</button>
        <img alt="">
        <p></p>
    </dialog>
</section>

@script
<script>
    const viewer = $wire.$el.querySelector('.pos-image-viewer');
    const image = viewer.querySelector('img');
    const caption = viewer.querySelector('p');

    $wire.on('open-pos-image', ({ src, caption: imageCaption }) => {
        image.src = src;
        image.alt = imageCaption;
        caption.textContent = imageCaption;
        viewer.showModal();
    });

    viewer.querySelector('.pos-image-close').addEventListener('click', () => viewer.close());
    viewer.addEventListener('click', (event) => {
        if (event.target === viewer) viewer.close();
    });
</script>
@endscript
