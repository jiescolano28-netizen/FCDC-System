<?php

namespace App\Livewire\Pos;

use App\Models\Inventory;
use Livewire\Component;

class PointOfSale extends Component
{
    public string $search = '';

    public function addToCart(int $inventoryId): void
    {
        $this->authorizePermission('pos.checkout');
        $item = Inventory::availableForSale()->findOrFail($inventoryId);
        $state = $this->state();
        $available = $this->availableQuantity($item, $state);
        $quantity = $state['cart'][$item->id]['quantity'] ?? 0;

        if ($quantity >= $available) {
            return;
        }

        $state['cart'][$item->id] = ['quantity' => $quantity + 1];
        $this->saveState($state);
    }

    public function changeQuantity(int $inventoryId, int $delta): void
    {
        $this->authorizePermission('pos.checkout');
        $state = $this->state();
        $line = $state['cart'][$inventoryId] ?? null;

        if ($line === null) {
            return;
        }

        $quantity = $line['quantity'] + ($delta < 0 ? -1 : 1);
        $item = Inventory::availableForSale()->find($inventoryId);
        $available = $item ? $this->availableQuantity($item, $state) : 0;

        if ($quantity <= 0 || $available <= 0) {
            unset($state['cart'][$inventoryId]);
        } else {
            $state['cart'][$inventoryId]['quantity'] = min($quantity, $available);
        }

        $this->saveState($state);
    }

    public function removeFromCart(int $inventoryId): void
    {
        $this->authorizePermission('pos.checkout');
        $state = $this->state();
        unset($state['cart'][$inventoryId]);
        $this->saveState($state);
    }

    public function checkout(): void
    {
        $this->authorizePermission('pos.checkout');
        $state = $this->state();
        $items = [];

        foreach ($state['cart'] as $inventoryId => $line) {
            $item = Inventory::availableForSale()->find($inventoryId);
            $quantity = (int) $line['quantity'];

            if ($item === null || $quantity < 1 || $quantity > $this->availableQuantity($item, $state)) {
                continue;
            }

            $items[] = [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'unit' => $item->unit,
                'quantity' => $quantity,
                'sellingPrice' => (float) $item->selling_price,
            ];
        }

        if ($items === []) {
            $state['cart'] = [];
            $this->saveState($state);

            return;
        }

        $subtotal = array_sum(array_map(fn (array $item) => $item['quantity'] * $item['sellingPrice'], $items));
        $tax = $this->taxFor($subtotal);
        $sales = $state['sales'];
        $saleId = 'S-'.(1050 + count($sales));
        $sale = [
            'id' => $saleId,
            'date' => now()->toDateString(),
            'items' => array_sum(array_column($items, 'quantity')),
            'total' => $subtotal + $tax,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'method' => 'Card',
            'lines' => $items,
        ];

        foreach ($items as $item) {
            $state['stock'][$item['id']] = ($state['stock'][$item['id']] ?? 0) + $item['quantity'];
        }

        array_unshift($sales, $sale);
        $state['sales'] = $sales;
        $state['cart'] = [];
        $state['lastSaleId'] = $saleId;
        $this->saveState($state);
    }

    public function viewReceipt(string $saleId): void
    {
        $this->authorizePermission('pos.checkout');
        $state = $this->state();

        if (collect($state['sales'])->contains('id', $saleId)) {
            $state['lastSaleId'] = $saleId;
            $this->saveState($state);
        }
    }

    public function closeReceipt(): void
    {
        $this->authorizePermission('pos.checkout');
        $state = $this->state();
        $state['lastSaleId'] = null;
        $this->saveState($state);
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
        $state = $this->state();
        $items = Inventory::availableForSale()
            ->where(function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('category', 'like', '%'.$this->search.'%');
            })
            ->get()
            ->map(function (Inventory $item) use ($state) {
                $item->simulated_qty = $this->availableQuantity($item, $state);

                return $item;
            })
            ->filter(fn (Inventory $item) => $item->simulated_qty >= 1);

        $cartItems = Inventory::query()->whereKey(array_keys($state['cart']))->get()->keyBy('id');
        $lines = collect($state['cart'])->map(function (array $line, int|string $id) use ($cartItems, $state) {
            $item = $cartItems->get((int) $id);

            if ($item === null || $item->selling_price === null) {
                return null;
            }

            $item->cart_quantity = $line['quantity'];
            $item->simulated_qty = max(0, (float) $item->qty - ($state['stock'][$item->id] ?? 0));

            return $item;
        })->filter()->values();
        $subtotal = $lines->sum(fn (Inventory $item) => $item->cart_quantity * (float) $item->selling_price);
        $sales = $state['sales'];
        $receipt = collect($sales)->firstWhere('id', $state['lastSaleId']);
        $settings = session()->get('demo.settings.employee.'.auth()->id(), []);

        return view('livewire.pos.point-of-sale', [
            'items' => $items,
            'lines' => $lines,
            'subtotal' => $subtotal,
            'tax' => $this->taxFor($subtotal),
            'sales' => $sales,
            'receipt' => $receipt,
            'settings' => $settings,
        ])->layout('layouts.app', ['title' => 'Point of sale']);
    }

    private function state(): array
    {
        return session()->get($this->sessionKey(), [
            'cart' => [],
            'sales' => [],
            'stock' => [],
            'lastSaleId' => null,
        ]);
    }

    private function saveState(array $state): void
    {
        session()->put($this->sessionKey(), $state);
    }

    private function sessionKey(): string
    {
        return 'demo.pos.employee.'.auth()->id();
    }

    private function availableQuantity(Inventory $item, array $state): int
    {
        return max(0, (int) floor((float) $item->qty - ($state['stock'][$item->id] ?? 0)));
    }


    private function taxFor(float $subtotal): float
    {
        return $subtotal * 0.12;
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
