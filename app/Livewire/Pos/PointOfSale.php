<?php

namespace App\Livewire\Pos;

use App\Models\Inventory;
use App\Models\PosTransaction;
use App\Models\PosTransactionLine;
use App\Models\PosVatRecord;
use App\Services\Accounting\PosSalePostingService;
use App\Support\TaxClassification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class PointOfSale extends Component
{
    use WithPagination;

    public string $search = '';

    public string $category = '';

    /** Product-list filter only; the sale classification is selected in the cart. */
    public string $taxClassification = '';

    /** Single tax classification applied to every line in the current sale. */
    public string $saleTaxClassification = '';

    public string $customerName = '';

    public string $paymentMethod = 'cash';

    public string $amountReceived = '';

    public string $paymentReference = '';

    #[Locked]
    public ?int $receiptId = null;

    public function mount(): void
    {
        $this->saleTaxClassification = $this->classificationForCart($this->cart()) ?? '';
    }

    public function addToCart(int $inventoryId): void
    {
        $this->authorizePermission('pos.checkout');
        $item = Inventory::availableForSale()->findOrFail($inventoryId);
        $cart = $this->cart();
        $current = (float) ($cart[$item->id]['quantity'] ?? 0);
        $available = (int) floor((float) $item->qty);
        $next = $current === 0.0 ? min(1, $available) : min($current + 1, $available);

        if ($next <= $current) {
            return;
        }

        $saleClassification = $this->saleTaxClassification !== ''
            ? $this->saleTaxClassification
            : ($this->classificationForCart($cart) ?? $item->taxClassification());

        $this->saleTaxClassification = $saleClassification;
        $cart[$item->id] = ['quantity' => $next];
        $this->saveCart($cart);
    }

    public function setSaleTaxClassification(string $classification): void
    {
        $this->authorizePermission('pos.checkout');

        if ($classification === '' && $this->cart() === []) {
            $this->saleTaxClassification = '';

            return;
        }

        if (! TaxClassification::isValid($classification)) {
            throw ValidationException::withMessages([
                'saleTaxClassification' => 'Select a valid tax classification for this sale.',
            ]);
        }

        $this->saleTaxClassification = $classification;
    }

    public function changeQuantity(int $inventoryId, int $delta): void
    {
        $this->authorizePermission('pos.checkout');
        $cart = $this->cart();
        $quantity = (float) ($cart[$inventoryId]['quantity'] ?? 0) + ($delta < 0 ? -1 : 1);
        $this->setCartQuantity($cart, $inventoryId, $quantity);
    }

    public function setQuantity(int $inventoryId, float $quantity): void
    {
        $this->authorizePermission('pos.checkout');
        $cart = $this->cart();
        $this->setCartQuantity($cart, $inventoryId, $quantity);
    }

    public function removeFromCart(int $inventoryId): void
    {
        $this->authorizePermission('pos.checkout');
        $cart = $this->cart();
        unset($cart[$inventoryId]);
        $this->saveCart($cart);

        if ($cart === []) {
            $this->saleTaxClassification = '';
        }
    }

    public function checkout(): void
    {
        $this->authorizePermission('pos.checkout');
        $this->resetValidation();
        $cart = $this->pruneUnavailableCart();

        if ($cart === []) {
            throw ValidationException::withMessages(['cart' => 'Add at least one item before checkout.']);
        }
        ksort($cart);

        if ($this->saleTaxClassification === '') {
            $this->saleTaxClassification = $this->classificationForCart($cart) ?? '';
        }

        $this->validate([
            'saleTaxClassification' => ['required', Rule::in(TaxClassification::all())],
            'customerName' => ['nullable', 'string', 'max:255'],
            'paymentMethod' => ['required', 'in:cash,card,bank_transfer'],
            'amountReceived' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
        ], [
            'saleTaxClassification.required' => 'Select the tax classification for this sale.',
            'saleTaxClassification.in' => 'Select a valid tax classification for this sale.',
        ]);

        if ($this->paymentMethod !== 'cash' && trim($this->paymentReference) === '') {
            throw ValidationException::withMessages(['paymentReference' => 'A reference number is required for card and bank transfer payments.']);
        }

        $transaction = DB::transaction(function () use ($cart): PosTransaction {
            $classification = $this->saleTaxClassification;
            $items = [];

            foreach ($cart as $inventoryId => $line) {
                $item = Inventory::query()->lockForUpdate()->find($inventoryId);
                $quantity = (float) ($line['quantity'] ?? 0);

                if ($item === null || $item->status !== 'active' || $item->selling_price === null
                    || $quantity <= 0 || floor($quantity) !== $quantity || $quantity > (float) $item->qty) {
                    throw ValidationException::withMessages(['cart' => 'An item is no longer available in the requested quantity. Review the cart and try again.']);
                }

                $quantityCents = (int) round($quantity * 100);
                $priceCents = (int) round((float) $item->selling_price * 100);
                $items[] = [
                    'inventory' => $item,
                    'quantity' => $quantity,
                    'tax_classification' => $classification,
                    'lineSubtotalCents' => (int) round($quantityCents * $priceCents / 100),
                ];
            }

            $subtotalCents = array_sum(array_column($items, 'lineSubtotalCents'));
            $vatCents = $this->vatCents($subtotalCents, $classification);
            $totalCents = $subtotalCents + $vatCents;
            $receivedCents = (int) round((float) $this->amountReceived * 100);

            if ($this->paymentMethod === 'cash' && $receivedCents < $totalCents) {
                throw ValidationException::withMessages(['amountReceived' => 'Cash received must cover the transaction total.']);
            }

            if ($this->paymentMethod !== 'cash' && $receivedCents !== $totalCents) {
                throw ValidationException::withMessages(['amountReceived' => 'Card and bank transfer payments must exactly match the transaction total.']);
            }

            $settings = session()->get('demo.settings.employee.'.auth()->id(), []);
            $transaction = PosTransaction::create([
                'transaction_number' => 'POS-'.now('Asia/Manila')->format('Ymd').'-'.Str::upper(Str::random(8)),
                'employee_id' => auth()->id(),
                'customer_name' => trim($this->customerName) ?: null,
                'subtotal' => $subtotalCents / 100,
                'tax_classification' => $classification,
                'vat_rate' => TaxClassification::rate($classification),
                'vat_amount' => $vatCents / 100,
                'total' => $totalCents / 100,
                'payment_method' => $this->paymentMethod,
                'amount_received' => $receivedCents / 100,
                'change_due' => max(0, $receivedCents - $totalCents) / 100,
                'payment_reference' => trim($this->paymentReference) ?: null,
                'status' => 'completed',
                'completed_at' => now('UTC'),
                'receipt_company_name' => $settings['companyName'] ?? null,
                'receipt_company_address' => $settings['address'] ?? null,
                'receipt_company_phone' => $settings['phone'] ?? null,
            ]);

            PosVatRecord::create([
                'pos_transaction_id' => $transaction->id,
                'tax_classification' => $classification,
                'taxable_sales' => $this->salesCentsFor($classification, $subtotalCents, TaxClassification::VATABLE) / 100,
                'zero_rated_sales' => $this->salesCentsFor($classification, $subtotalCents, TaxClassification::ZERO_RATED) / 100,
                'exempt_sales' => $this->salesCentsFor($classification, $subtotalCents, TaxClassification::VAT_EXEMPT) / 100,
                'non_vat_sales' => $this->salesCentsFor($classification, $subtotalCents, TaxClassification::NON_VAT) / 100,
                'vat_rate' => $transaction->vat_rate,
                'output_vat' => $transaction->vat_amount,
                'total' => $transaction->total,
                'completed_at' => $transaction->completed_at,
            ]);
            $lineCostSnapshots = app(PosSalePostingService::class)
                ->post($transaction, $items, auth()->id());

            $vatRemaining = $vatCents;
            $lastIndex = array_key_last($items);

            foreach ($items as $index => $line) {
                /** @var Inventory $item */
                $item = $line['inventory'];
                $quantity = $line['quantity'];
                $lineSubtotalCents = $line['lineSubtotalCents'];
                $lineVatCents = TaxClassification::isVatable($classification)
                    ? ($index === $lastIndex
                        ? $vatRemaining
                        : min($vatRemaining, (int) round($lineSubtotalCents * TaxClassification::rate($classification))))
                    : 0;
                $vatRemaining -= $lineVatCents;

                PosTransactionLine::create([
                    'pos_transaction_id' => $transaction->id,
                    'inventory_id' => $item->id,
                    'inventory_code' => $item->code,
                    'item_name' => $item->name,
                    'category' => $item->category,
                    'tax_classification' => $classification,
                    'unit' => $item->unit,
                    'quantity' => $quantity,
                    'selling_price' => $item->selling_price,
                    'unit_cost' => $lineCostSnapshots[$item->id]['unit_cost'],
                    'line_cost_cents' => $lineCostSnapshots[$item->id]['line_cost_cents'],
                    'line_subtotal' => $lineSubtotalCents / 100,
                    'vat_amount' => $lineVatCents / 100,
                    'line_total' => ($lineSubtotalCents + $lineVatCents) / 100,
                ]);
            }

            return $transaction;
        });

        $this->saveCart([]);
        $this->resetPage();
        $this->receiptId = $transaction->id;
        $this->saleTaxClassification = '';
        $this->amountReceived = '';
        $this->paymentReference = '';
        $this->customerName = '';
    }

    public function viewReceipt(int $transactionId): void
    {
        $this->authorizePermission('pos.view');
        $this->receiptId = PosTransaction::query()->where('status', 'completed')->findOrFail($transactionId)->id;
    }

    public function closeReceipt(): void
    {
        $this->authorizePermission('pos.view');
        $this->receiptId = null;
    }

    public function viewImage(int $inventoryId): void
    {
        $item = Inventory::availableForSale()->findOrFail($inventoryId);

        if ($item->image) {
            $this->dispatch('open-pos-image', src: asset('storage/'.$item->image), caption: $item->name);
        }
    }

    public function render()
    {
        $itemsQuery = Inventory::availableForSale()
            ->where('qty', '>', 0)
            ->when($this->search !== '', fn ($query) => $query->where(function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('category', 'like', '%'.$this->search.'%');
            }))
            ->when($this->category !== '', fn ($query) => $query->where('category', $this->category))
            ->when(
                in_array($this->taxClassification, TaxClassification::all(), true),
                fn ($query) => $query->where('tax_classification', $this->taxClassification),
            );
        $items = $itemsQuery->get();
        $categories = Inventory::availableForSale()->where('qty', '>', 0)->distinct()->orderBy('category')->pluck('category');
        $cart = $this->pruneUnavailableCart();
        $cartItems = Inventory::query()->whereKey(array_keys($cart))->get()->keyBy('id');
        $lines = collect($cart)->map(function (array $line, int|string $id) use ($cartItems) {
            $item = $cartItems->get((int) $id);

            if ($item === null || $item->selling_price === null) {
                return null;
            }

            $item->cart_quantity = (float) $line['quantity'];

            return $item;
        })->filter()->values();

        $selectedClassification = TaxClassification::isValid($this->saleTaxClassification)
            ? $this->saleTaxClassification
            : $lines->first()?->taxClassification();
        $lineSubtotalCents = fn (Inventory $item): int => (int) round(
            (int) round($item->cart_quantity * 100) * (int) round((float) $item->selling_price * 100) / 100,
        );
        $subtotalCents = $lines->sum($lineSubtotalCents);
        $vatCents = $selectedClassification === null
            ? 0
            : $this->vatCents($subtotalCents, $selectedClassification);
        $history = PosTransaction::query()->with(['lines', 'vatRecord'])->where('status', 'completed')->latest('completed_at')->paginate(20);
        $receipt = $this->receiptId
            ? PosTransaction::query()->with(['lines', 'vatRecord'])->where('status', 'completed')->find($this->receiptId)
            : null;

        return view('livewire.pos.point-of-sale', [
            'items' => $items,
            'categories' => $categories,
            'taxClassifications' => TaxClassification::options(),
            'lines' => $lines,
            'subtotal' => $subtotalCents / 100,
            'cartTaxClassification' => $selectedClassification,
            'vatRate' => $selectedClassification === null ? 0.0 : TaxClassification::rate($selectedClassification),
            'vat' => $vatCents / 100,
            'total' => ($subtotalCents + $vatCents) / 100,
            'history' => $history,
            'receipt' => $receipt,
        ])->layout('layouts.app', ['title' => 'Point of sale']);
    }

    private function vatCents(int $subtotalCents, string $classification): int
    {
        return (int) round($subtotalCents * TaxClassification::rate($classification));
    }

    private function salesCentsFor(string $saleClassification, int $subtotalCents, string $classification): int
    {
        return $saleClassification === $classification ? $subtotalCents : 0;
    }

    private function cart(): array
    {
        return session()->get($this->sessionKey(), []);
    }

    private function saveCart(array $cart): void
    {
        session()->put($this->sessionKey(), $cart);
    }

    /**
     * Drop cart lines whose inventory item was deleted, deactivated, or no longer has a selling price.
     * Those lines cannot be shown or edited in the cart, so keeping them would block every checkout.
     */
    private function pruneUnavailableCart(): array
    {
        $cart = $this->cart();

        if ($cart === []) {
            return $cart;
        }

        $items = Inventory::query()
            ->whereKey(array_keys($cart))
            ->get()
            ->keyBy('id');

        $pruned = array_filter($cart, function ($line, $id) use ($items): bool {
            $item = $items->get((int) $id);

            return $item !== null
                && $item->status === Inventory::STATUS_ACTIVE
                && $item->selling_price !== null;
        }, ARRAY_FILTER_USE_BOTH);

        if (count($pruned) !== count($cart)) {
            $this->saveCart($pruned);

            if ($pruned === []) {
                $this->saleTaxClassification = '';
            }
        }

        return $pruned;
    }

    private function sessionKey(): string
    {
        return 'pos.employee.'.auth()->id().'.cart';
    }

    private function setCartQuantity(array $cart, int $inventoryId, float $quantity): void
    {
        $item = Inventory::availableForSale()->find($inventoryId);

        if ($item === null) {
            unset($cart[$inventoryId]);
        } elseif ($quantity <= 0 || floor($quantity) !== $quantity || $quantity > (float) $item->qty) {
            throw ValidationException::withMessages(['cart' => 'Quantity must be a positive whole number and not exceed available stock.']);
        } else {
            $cart[$inventoryId] = ['quantity' => (int) $quantity];
        }

        $this->saveCart($cart);

        if ($cart === []) {
            $this->saleTaxClassification = '';
        }
    }

    private function classificationForCart(array $cart): ?string
    {
        if ($cart === []) {
            return null;
        }

        $item = Inventory::query()->find(array_key_first($cart));

        return $item?->taxClassification();
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
