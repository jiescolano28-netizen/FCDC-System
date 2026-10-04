<section class="inventory-page" aria-labelledby="roles-heading">
    <div class="columns" style="justify-content:space-between; margin-bottom:18px;">
        <div>
            <h1 id="roles-heading">Role management</h1>
            <p class="muted">Create roles and select access from the application permission catalog.</p>
        </div>
    </div>

    @if (auth()->user()->can('roles.create') || ($editingId && auth()->user()->can('roles.update')))
        <div class="panel">
            <h2>{{ $editingId ? 'Edit role' : 'Create role' }}</h2>
            <form wire:submit="save">
                <div style="max-width:440px;">
                    <label for="role-name">Role name</label>
                    <input id="role-name" wire:model="name" autocomplete="off">
                    @error('name') <span class="error">{{ $message }}</span> @enderror
                </div>

                <fieldset style="margin-top:16px;">
                    <legend>Permissions</legend>
                    @foreach ($permissionGroups as $group => $permissions)
                        <fieldset style="margin:12px 0;">
                            <legend>{{ $group }}</legend>
                            @foreach ($permissions as $permission => $label)
                                <label style="display:inline-flex; align-items:center; gap:6px; margin:6px 14px 6px 0;">
                                    <input type="checkbox" value="{{ $permission }}" wire:model="selectedPermissionNames">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </fieldset>
                    @endforeach
                    @error('selectedPermissionNames.*') <span class="error">{{ $message }}</span> @enderror
                </fieldset>

                <div class="actions" style="margin-top:14px;">
                    <button class="primary" type="submit">{{ $editingId ? 'Save role' : 'Create role' }}</button>
                    @if ($editingId)
                        <button class="secondary" type="button" wire:click="resetForm">Cancel</button>
                    @endif
                </div>
            </form>
        </div>
    @endif

    <div class="panel table-wrap">
        <table>
            <thead>
                <tr><th>Role</th><th>Permissions</th><th>Employees</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($roles as $role)
                    <tr wire:key="role-{{ $role->id }}">
                        <td>
                            {{ $role->name }}
                            @if ($role->name === \App\Support\RolePermissionCatalog::ADMIN_ROLE)
                                <span class="status">Protected system role</span>
                            @endif
                        </td>
                        <td>{{ $role->permissions->pluck('name')->join(', ') ?: 'No permissions' }}</td>
                        <td>{{ $role->users()->count() }}</td>
                        <td>
                            @if ($role->name !== \App\Support\RolePermissionCatalog::ADMIN_ROLE)
                                <div class="actions">
                                    @can('roles.update')
                                        <button class="secondary" type="button" wire:click="edit({{ $role->id }})">Edit</button>
                                    @endcan
                                    @can('roles.delete')
                                        <button class="danger" type="button" wire:click="delete({{ $role->id }})" wire:confirm="Delete role {{ $role->name }}?">Delete</button>
                                    @endcan
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No roles have been created.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
