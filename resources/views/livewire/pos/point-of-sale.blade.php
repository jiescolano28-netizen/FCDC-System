@php($money = fn ($amount) => '₱'.number_format($amount, 2))
<section class="pos-page" aria-labelledby="pos-heading">
    <header class="pos-heading">
        <div>
            <h1 id="pos-heading">Point of sale</h1>
            <p class="page-subtitle">Temporary demonstration activity. Checkout does not record sales or change inventory.</p>
        </div>
    </header>

    <div class="pos-layout">
        <div class="pos-products">
            <label class="pos-search-label" for="pos-search">Search materials</label>
            <input id="pos-search" class="pos-search" type="search" placeholder="Search materials to sell..." wire:model.live.debounce.300ms="search">

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
                            <strong>{{ $money((float) $item->selling_price) }}</strong>
                            <span>{{ number_format($item->simulated_qty, 2) }} {{ $item->unit }} left</span>
                        </div>
                        <button class="pos-add-button" type="button" wire:click="addToCart({{ $item->id }})">Add to Sale</button>
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
                        <div class="pos-cart-name">{{ $item->name }}<span>{{ $money((float) $item->selling_price) }} / {{ $item->unit }}</span></div>
                        <div class="pos-quantity-controls">
                            <button type="button" wire:click="changeQuantity({{ $item->id }}, -1)" aria-label="Decrease {{ $item->name }} quantity">−</button>
                            <span>{{ $item->cart_quantity }}</span>
                            <button type="button" wire:click="changeQuantity({{ $item->id }}, 1)" aria-label="Increase {{ $item->name }} quantity">+</button>
                            <button class="pos-remove-button" type="button" wire:click="removeFromCart({{ $item->id }})" aria-label="Remove {{ $item->name }}">×</button>
                        </div>
                    </div>
                @empty
                    <p class="pos-cart-empty">Cart is empty. Tap a material to add it.</p>
                @endforelse
            </div>

            <div class="pos-cart-totals">
                <div><span>Subtotal</span><span>{{ $money($subtotal) }}</span></div>
                <div><span>VAT (12%)</span><span>{{ $money($tax) }}</span></div>
                <strong><span>Total</span><span>{{ $money($subtotal + $tax) }}</span></strong>
            </div>
            <button class="pos-checkout-button" type="button" wire:click="checkout" @disabled($lines->isEmpty())>Charge {{ $money($subtotal + $tax) }}</button>
            <p class="pos-demo-note" role="note">Demonstration checkout only. No payment is processed and no sale or inventory record is created.</p>

            @if ($receipt)
                <p class="pos-confirmation" role="status">Demo sale {{ $receipt['id'] }} completed — demonstration activity, not a recorded sale.</p>
            @endif
        </aside>
    </div>

    @if (count($sales))
        <section class="pos-sales-history" aria-labelledby="pos-history-heading">
            <h2 id="pos-history-heading">Completed demo sales</h2>
            @foreach ($sales as $sale)
                <div class="pos-sale-row" wire:key="demo-sale-{{ $sale['id'] }}">
                    <span>{{ $sale['id'] }} · {{ $sale['date'] }} · {{ $sale['items'] }} item(s) · {{ $money($sale['total']) }}</span>
                    <button type="button" wire:click="viewReceipt('{{ $sale['id'] }}')">View demo receipt</button>
                </div>
            @endforeach
        </section>
    @endif

    @if ($receipt)
        <div class="pos-receipt-overlay" role="dialog" aria-modal="true" aria-labelledby="receipt-heading">
            <article class="pos-receipt" id="pos-receipt-print">
                <h2 id="receipt-heading">Demonstration receipt · Not a recorded sale</h2>
                <header>
                    <strong>{{ $settings['companyName'] ?? 'Fabellion Construction and Development Corp.' }}</strong>
                    <span>{{ $settings['address'] ?? 'San Mateo, Rizal' }}</span>
                    <span>{{ $settings['phone'] ?? '(951) 555-0148' }}</span>
                </header>
                <p class="pos-receipt-meta"><span>Demo receipt #{{ $receipt['id'] }}</span><span>{{ $receipt['date'] }}</span></p>
                <table>
                    <tbody>
                        @foreach ($receipt['lines'] as $line)
                            <tr>
                                <td>{{ $line['name'] }}<small>{{ $line['quantity'] }} x {{ $money($line['sellingPrice']) }}</small></td>
                                <td>{{ $money($line['quantity'] * $line['sellingPrice']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="pos-receipt-totals"><div><span>Subtotal</span><span>{{ $money($receipt['subtotal']) }}</span></div><div><span>VAT (12%)</span><span>{{ $money($receipt['tax']) }}</span></div><strong><span>Total</span><span>{{ $money($receipt['total']) }}</span></strong></div>
                <p class="pos-receipt-disclaimer">For demonstration only. No payment was processed and this is not a recorded business sale.</p>
                <footer class="pos-receipt-actions">
                    <button type="button" wire:click="closeReceipt">Close</button>
                    <button type="button" onclick="document.body.classList.add('printing-pos-receipt'); window.print(); setTimeout(() => document.body.classList.remove('printing-pos-receipt'), 100)">Print demonstration receipt</button>
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
