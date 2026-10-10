<?php

namespace App\Livewire\Pos;

use App\Models\Inventory;
use App\Models\PosTransaction;
use App\Models\PosTransactionLine;
use App\Models\PosVatRecord;
use App\Services\Accounting\PosSalePostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class PointOfSale extends Component
{
    use WithPagination;

    private const VAT_RATE = 0.12;

    public string $search = '';

    public string $category = '';

    public string $customerName = '';

    public string $paymentMethod = 'cash';

    public string $amountReceived = '';

    public string $paymentReference = '';

    #[Locked]
    public ?int $receiptId = null;

    public function addToCart(int $inventoryId): void
    {
        $this->authorizePermission('pos.checkout');
        $item = Inventory::availableForSale()->findOrFail($inventoryId);
        $cart = $this->cart();
        $current = (float) ($cart[$item->id]['quantity'] ?? 0);
        $next = $current === 0.0 ? min(1, (float) $item->qty) : min($current + 1, (float) $item->qty);

        if ($next > $current) {
            $cart[$item->id] = ['quantity' => $next];
            $this->saveCart($cart);
        }
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
    }

    public function checkout(): void
    {
        $this->authorizePermission('pos.checkout');
        $this->resetValidation();
        $cart = $this->cart();

        if ($cart === []) {
            throw ValidationException::withMessages(['cart' => 'Add at least one item before checkout.']);
        }
        ksort($cart);

        $this->validate([
            'customerName' => ['nullable', 'string', 'max:255'],
            'paymentMethod' => ['required', 'in:cash,card,bank_transfer'],
            'amountReceived' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->paymentMethod !== 'cash' && trim($this->paymentReference) === '') {
            throw ValidationException::withMessages(['paymentReference' => 'A reference number is required for card and bank transfer payments.']);
        }

        $transaction = DB::transaction(function () use ($cart): PosTransaction {
            $items = [];

            foreach ($cart as $inventoryId => $line) {
                $item = Inventory::query()->lockForUpdate()->find($inventoryId);
                $quantity = (float) ($line['quantity'] ?? 0);

                if ($item === null || $item->status !== 'active' || $item->selling_price === null
                    || $quantity <= 0 || round($quantity, 2) !== $quantity || $quantity > (float) $item->qty) {
                    throw ValidationException::withMessages(['cart' => 'An item is no longer available in the requested quantity. Review the cart and try again.']);
                }

                $quantityCents = (int) round($quantity * 100);
                $priceCents = (int) round((float) $item->selling_price * 100);
                $items[] = [
                    'inventory' => $item,
                    'quantity' => $quantity,
                    'lineSubtotalCents' => (int) round($quantityCents * $priceCents / 100),
                ];
            }

            $subtotalCents = array_sum(array_column($items, 'lineSubtotalCents'));
            $vatCents = $this->vatCents($subtotalCents);
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
                'transaction_number' => 'POS-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'employee_id' => auth()->id(),
                'customer_name' => trim($this->customerName) ?: null,
                'subtotal' => $subtotalCents / 100,
                'vat_rate' => self::VAT_RATE,
                'vat_amount' => $vatCents / 100,
                'total' => $totalCents / 100,
                'payment_method' => $this->paymentMethod,
                'amount_received' => $receivedCents / 100,
                'change_due' => max(0, $receivedCents - $totalCents) / 100,
                'payment_reference' => trim($this->paymentReference) ?: null,
                'status' => 'completed',
                'completed_at' => now(),
                'receipt_company_name' => $settings['companyName'] ?? null,
                'receipt_company_address' => $settings['address'] ?? null,
                'receipt_company_phone' => $settings['phone'] ?? null,
            ]);

            PosVatRecord::create([
                'pos_transaction_id' => $transaction->id,
                'taxable_sales' => $transaction->subtotal,
                'vat_rate' => $transaction->vat_rate,
                'output_vat' => $transaction->vat_amount,
                'total' => $transaction->total,
                'completed_at' => $transaction->completed_at,
            ]);
            $lineCostSnapshots = app(PosSalePostingService::class)
                ->post($transaction, $items, auth()->id());

            $vatRemaining = $vatCents;
            foreach ($items as $index => $line) {
                /** @var Inventory $item */
                $item = $line['inventory'];
                $quantity = $line['quantity'];
                $lineSubtotalCents = $line['lineSubtotalCents'];
                $lineVatCents = $index === array_key_last($items)
                    ? $vatRemaining
                    : min($vatRemaining, (int) round($lineSubtotalCents * 0.12));
                $vatRemaining -= $lineVatCents;

                PosTransactionLine::create([
                    'pos_transaction_id' => $transaction->id,
                    'inventory_id' => $item->id,
                    'inventory_code' => $item->code,
                    'item_name' => $item->name,
                    'category' => $item->category,
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
            ->when($this->category !== '', fn ($query) => $query->where('category', $this->category));
        $items = $itemsQuery->get();
        $categories = Inventory::availableForSale()->where('qty', '>', 0)->distinct()->orderBy('category')->pluck('category');
        $cart = $this->cart();
        $cartItems = Inventory::query()->whereKey(array_keys($cart))->get()->keyBy('id');
        $lines = collect($cart)->map(function (array $line, int|string $id) use ($cartItems) {
            $item = $cartItems->get((int) $id);

            if ($item === null || $item->selling_price === null) {
                return null;
            }

            $item->cart_quantity = (float) $line['quantity'];

            return $item;
        })->filter()->values();
        $subtotalCents = $lines->sum(fn (Inventory $item) => (int) round(
            (int) round($item->cart_quantity * 100) * (int) round((float) $item->selling_price * 100) / 100,
        ));
        $subtotal = $subtotalCents / 100;
        $history = PosTransaction::query()->with('lines')->where('status', 'completed')->latest('completed_at')->paginate(20);
        $receipt = $this->receiptId
            ? PosTransaction::query()->with('lines')->where('status', 'completed')->find($this->receiptId)
            : null;

        return view('livewire.pos.point-of-sale', [
            'items' => $items,
            'categories' => $categories,
            'lines' => $lines,
            'subtotal' => $subtotal,
            'vat' => $this->vatCents($subtotalCents) / 100,
            'history' => $history,
            'receipt' => $receipt,
        ])->layout('layouts.app', ['title' => 'Point of sale']);
    }

    private function vatCents(int $subtotalCents): int
    {
        return (int) round($subtotalCents * self::VAT_RATE);
    }

    private function cart(): array
    {
        return session()->get($this->sessionKey(), []);
    }

    private function saveCart(array $cart): void
    {
        session()->put($this->sessionKey(), $cart);
    }

    private function sessionKey(): string
    {
        return 'pos.employee.'.auth()->id().'.cart';
    }

    private function setCartQuantity(array $cart, int $inventoryId, float $quantity): void
    {
        $item = Inventory::availableForSale()->find($inventoryId);

        if ($item === null || $quantity <= 0) {
            unset($cart[$inventoryId]);
        } elseif (round($quantity, 2) !== $quantity || $quantity > (float) $item->qty) {
            throw ValidationException::withMessages(['cart' => 'Quantity must be positive, use at most two decimal places, and not exceed available stock.']);
        } else {
            $cart[$inventoryId] = ['quantity' => $quantity];
        }

        $this->saveCart($cart);
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
