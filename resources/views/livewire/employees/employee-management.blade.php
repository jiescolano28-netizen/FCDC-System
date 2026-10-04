<section class="inventory-page" aria-labelledby="employees-heading">
    <div class="columns employee-heading">
        <div>
            <h1 id="employees-heading">Employee management</h1>
            <p class="muted">Create employee accounts and manage their access roles.</p>
        </div>
        @can('employees.delete')
            <label><input type="checkbox" wire:model.live="showDeleted"> Show deleted employees</label>
        @endcan
        @can('employees.create')
            <button class="primary" type="button" wire:click="create">Add employee</button>
        @endcan
    </div>

    @if (auth()->user()->can('employees.create') || auth()->user()->can('employees.update'))
        <dialog class="employee-modal" id="employee-modal" wire:ignore.self aria-labelledby="employee-modal-title">
            <div class="employee-modal-header">
                <div>
                    <h2 id="employee-modal-title">{{ $editingId ? 'Edit employee' : 'Add employee' }}</h2>
                    <p class="muted">Enter account details and assign access roles.</p>
                </div>
                <button class="secondary" type="button" data-close-employee-modal aria-label="Close dialog">×</button>
            </div>
            <form wire:submit="save">
                <div class="fields">
                    <div>
                        <label for="employee-username">Username</label>
                        <input id="employee-username" autocomplete="username" wire:model="username">
                        @error('username') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="employee-email">Email</label>
                        <input id="employee-email" type="email" autocomplete="email" wire:model="email">
                        @error('email') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="employee-password">{{ $editingId ? 'New password (leave blank to keep current)' : 'Password' }}</label>
                        <input id="employee-password" type="password" autocomplete="new-password" wire:model="password">
                        @error('password') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>

                @can('employees.assign-roles')
                    <div class="employee-roles">
                        <label for="employee-roles">Roles</label>
                        @if ($roles->isEmpty())
                            <span class="muted">No assignable roles are available. Create a role in Role management first.</span>
                        @else
                            <div wire:ignore>
                                <select id="employee-roles" multiple data-role-multiselect aria-describedby="employee-roles-help">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        @if ($roles->isNotEmpty())
                            <span class="muted" id="employee-roles-help">Select one or more roles.</span>
                        @endif
                        @error('selectedRoleIds') <span class="error">{{ $message }}</span> @enderror
                    </div>
                @endcan

                <div class="actions employee-modal-actions">
                    <button class="secondary" type="button" data-close-employee-modal>Cancel</button>
                    <button class="primary" type="submit">{{ $editingId ? 'Save changes' : 'Create employee' }}</button>
                </div>
            </form>
        </dialog>
    @endif

    <div class="toolbar panel">
        <div style="flex:1; min-width:220px;">
            <label for="employee-search">Search employees</label>
            <input id="employee-search" type="search" placeholder="Username or email" wire:model.live.debounce.300ms="search">
        </div>
    </div>

    <div class="panel table-wrap">
        <table>
            <thead>
                <tr><th>Username</th><th>Email</th><th>Roles</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr wire:key="employee-{{ $employee->id }}">
                        <td>{{ $employee->username }}</td>
                        <td>{{ $employee->email }}</td>
                        <td>{{ $employee->roles->pluck('name')->join(', ') ?: 'No roles assigned' }}</td>
                        <td>
                            <div class="actions">
                                @if ($employee->trashed())
                                    @can('employees.delete')
                                        <button class="secondary" type="button" wire:click="restore({{ $employee->id }})">Restore</button>
                                    @endcan
                                @else
                                    @can('employees.update')
                                        <button class="secondary" type="button" wire:click="edit({{ $employee->id }})">Edit</button>
                                    @endcan
                                    @can('employees.delete')
                                        <button class="danger" type="button" data-delete-employee="{{ $employee->id }}" data-employee-name="{{ $employee->username }}">Delete</button>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No employees match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
