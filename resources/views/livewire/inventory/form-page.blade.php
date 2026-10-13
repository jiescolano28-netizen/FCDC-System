<section class="inventory-page">
    <div class="columns" style="justify-content:space-between; margin-bottom:18px;">
        <div>
            <h1>{{ $editingId ? (auth()->user()->can('inventory.update') ? 'Edit inventory item' : 'Inventory stock') : 'Add inventory item' }}</h1>
            <p class="muted">{{ $editingId ? (auth()->user()->can('inventory.update') ? 'Update the inventory item details.' : 'Record stock changes for this inventory item.') : 'Create an inventory item to manage its stock.' }}</p>
        </div>
        <a class="secondary" href="{{ route('inventory.management') }}">Back to Inventory Management</a>
    </div>

    @include('livewire.inventory.form')
</section>
