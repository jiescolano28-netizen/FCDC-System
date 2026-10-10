@php($money = fn ($amount) => '₱'.number_format((float) $amount, 2))
<section class="pos-page" aria-labelledby="pos-heading">
    <header class="pos-heading">
        <div>
            <h1 id="pos-heading">Point of sale</h1>
            <p class="page-subtitle">Completed transactions are recorded with 12% VAT on VAT-exclusive prices.</p>
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
            <h2 id="pos-cart-heading">Current Sale</h2>
            <div class="pos-cart-lines">
                @forelse ($lines as $item)
                    <div class="pos-cart-line" wire:key="cart-line-{{ $item->id }}">
                        <div class="pos-cart-name">{{ $item->name }}<span>{{ $money($item->selling_price) }} / {{ $item->unit }}</span></div>
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

            <div class="pos-cart-totals">
                <div><span>Subtotal</span><span>{{ $money($subtotal) }}</span></div>
                <div><span>VAT (12%)</span><span>{{ $money($vat) }}</span></div>
                <strong><span>Total</span><span>{{ $money($subtotal + $vat) }}</span></strong>
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
                    @error('cart') <p class="settings-error">{{ $message }}</p> @enderror
                    @error('amountReceived') <p class="settings-error">{{ $message }}</p> @enderror
                    @error('paymentReference') <p class="settings-error">{{ $message }}</p> @enderror
                    @error('paymentMethod') <p class="settings-error">{{ $message }}</p> @enderror
                    <button class="pos-checkout-button" type="button" wire:click="checkout" @disabled($lines->isEmpty())>Complete transaction · {{ $money($subtotal + $vat) }}</button>
                </div>
            @endcan
            @if (session()->has('status'))
                <p class="pos-confirmation" role="status">{{ session('status') }}</p>
            @endif
        </aside>
    </div>

    <section class="pos-sales-history" aria-labelledby="pos-history-heading">
        <h2 id="pos-history-heading">Transaction history</h2>
        <div class="reports-table-wrap">
            <table class="reports-table">
                <thead><tr><th>Transaction</th><th>Date</th><th>Customer</th><th>Items</th><th class="numeric">Amount</th><th class="numeric">VAT</th><th>Payment</th><th>Status</th><th>Receipt</th></tr></thead>
                <tbody>
                    @forelse ($history as $transaction)
                        <tr wire:key="pos-transaction-{{ $transaction->id }}">
                            <td>{{ $transaction->transaction_number }}</td>
                            <td>{{ $transaction->completed_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $transaction->customer_name ?: '—' }}</td>
                            <td>{{ $transaction->lines->map(fn ($line) => $line->item_name.' × '.number_format((float) $line->quantity, 2).' '.$line->unit)->implode(', ') }}</td>
                            <td class="numeric">{{ $money($transaction->total) }}</td>
                            <td class="numeric">{{ $money($transaction->vat_amount) }}</td>
                            <td>{{ str_replace('_', ' ', ucfirst($transaction->payment_method)) }}</td>
                            <td>{{ ucfirst($transaction->status) }}</td>
                            <td><button type="button" wire:click="viewReceipt({{ $transaction->id }})">View receipt</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="reports-empty">No completed transactions.</td></tr>
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
                <p class="pos-receipt-meta"><span>{{ $receipt->transaction_number }}</span><span>{{ $receipt->completed_at->format('Y-m-d H:i') }}</span></p>
                <p>Customer: {{ $receipt->customer_name ?: '—' }}</p>
                <table><tbody>
                    @foreach ($receipt->lines as $line)
                        <tr><td>{{ $line->item_name }}<small>{{ number_format((float) $line->quantity, 2) }} {{ $line->unit }} × {{ $money($line->selling_price) }}</small></td><td>{{ $money($line->line_subtotal) }}</td></tr>
                    @endforeach
                </tbody></table>
                <div class="pos-receipt-totals">
                    <div><span>Subtotal</span><span>{{ $money($receipt->subtotal) }}</span></div>
                    <div><span>VAT (12%)</span><span>{{ $money($receipt->vat_amount) }}</span></div>
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
