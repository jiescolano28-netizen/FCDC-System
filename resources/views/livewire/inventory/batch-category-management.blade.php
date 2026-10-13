<section class="inventory-page" aria-labelledby="batch-categories-heading">
    <div class="columns" style="justify-content:space-between; margin-bottom:18px;">
        <div>
            <h1 id="batch-categories-heading">Inventory batch categories</h1>
            <p class="muted">Create and manage categories for grouping inventory batches.</p>
        </div>
        @can('inventory.create')
            <button class="primary" type="button" wire:click="create">Add batch category</button>
        @endcan
    </div>

    @canany(['inventory.create', 'inventory.update'])
        <dialog class="employee-modal batch-category-modal" id="batch-category-modal" wire:ignore.self aria-labelledby="batch-category-modal-title">
            <div class="employee-modal-header">
                <div>
                    <h2 id="batch-category-modal-title">{{ $editingId ? 'Edit batch category' : 'New batch category' }}</h2>
                    <p class="muted">Create and manage categories for grouping inventory batches.</p>
                </div>
                <button class="secondary" type="button" data-close-batch-category-modal aria-label="Close dialog">×</button>
            </div>
            <form wire:submit="save">
                <div class="inventory-form-fields">
                    <div class="inventory-form-field">
                        <label for="batch-category-name">Name</label>
                        <input id="batch-category-name" wire:model="name" maxlength="255">
                        @error('name') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="inventory-form-field">
                        <label for="batch-category-description">Description (optional)</label>
                        <textarea id="batch-category-description" wire:model="description"></textarea>
                        @error('description') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="actions employee-modal-actions">
                    <button class="secondary" type="button" data-close-batch-category-modal>Cancel</button>
                    <button class="primary" type="submit">{{ $editingId ? 'Save changes' : 'Create category' }}</button>
                </div>
            </form>
        </dialog>
    @endcanany


    <div class="toolbar panel inventory-toolbar">
        <div class="inventory-search">
            <label for="batch-category-search">Search categories</label>
            <input id="batch-category-search" type="search" wire:model.live.debounce.300ms="search">
        </div>
        @can('inventory.delete')
            <label><input type="checkbox" wire:model.live="showDeleted"> Show deleted categories</label>
        @endcan
    </div>

    <div class="panel" style="overflow-x:auto;">
        <table>
            <thead><tr><th>Name</th><th>Description</th><th>Created by</th><th>Updated by</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr wire:key="batch-category-{{ $category->id }}">
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->description ?: '—' }}</td>
                        <td>{{ $category->creator?->username ?? '—' }}</td>
                        <td>{{ $category->updater?->username ?? '—' }}</td>
                        <td>
                            <div class="actions">
                                @if ($category->trashed())
                                    @can('inventory.delete')
                                        <button class="secondary" type="button" wire:click="restore({{ $category->id }})">Restore</button>
                                    @endcan
                                @else
                                    @can('inventory.update')
                                        <button class="secondary" type="button" wire:click="edit({{ $category->id }})">Edit</button>
                                    @endcan
                                    @can('inventory.delete')
                                        <button class="danger" type="button" wire:click="delete({{ $category->id }})" wire:confirm="Delete {{ $category->name }}?">Delete</button>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No batch categories found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
