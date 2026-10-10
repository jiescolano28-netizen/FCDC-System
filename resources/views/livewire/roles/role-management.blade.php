<section class="role-setup" aria-labelledby="roles-heading" data-role-setup>
    <header class="role-header">
        <div>
            <p class="role-eyebrow">ACCESS CONTROL <span>·</span> ADMINISTRATION</p>
            <h1 id="roles-heading">Roles &amp; permissions</h1>
            <p class="role-subtitle">Manage access levels and keep every team member in the right role.</p>
        </div>
    </header>

    @error('roles') <p class="role-feedback role-feedback-error" role="alert">{{ $message }}</p> @enderror
    @error('name') <p class="role-feedback role-feedback-error" role="alert">{{ $message }}</p> @enderror

    <nav class="role-tabs" aria-label="Role setup views">
        <button class="role-tab {{ $activeTab === 'permissions' ? 'active' : '' }}" type="button" wire:click="setActiveTab('permissions')" aria-selected="{{ $activeTab === 'permissions' ? 'true' : 'false' }}">Permissions</button>
        @can('employees.assign-roles')
            <button class="role-tab {{ $activeTab === 'users' ? 'active' : '' }}" type="button" wire:click="setActiveTab('users')" aria-selected="{{ $activeTab === 'users' ? 'true' : 'false' }}">User assignments <span>{{ $employees->count() }}</span></button>
        @endcan
    </nav>

    <div class="role-layout" data-view="permissions" @if ($activeTab !== 'permissions') hidden @endif>
        <aside class="role-list-panel" aria-label="Roles">
            <div class="role-list-heading"><div><h2>Roles</h2><span>Access profiles</span></div><span class="role-count">{{ $roles->count() }}</span></div>
            <div class="role-cards">
                @forelse ($roles as $role)
                    <button type="button" class="role-card {{ $role->id === $selectedRoleId ? 'selected' : '' }}" wire:click="selectRole({{ $role->id }})" aria-pressed="{{ $role->id === $selectedRoleId ? 'true' : 'false' }}">
                        <span class="role-card-icon {{ $loop->iteration % 4 === 1 ? 'violet' : ($loop->iteration % 4 === 2 ? 'blue' : ($loop->iteration % 4 === 3 ? 'green' : 'amber')) }}">{{ mb_substr($role->name, 0, 1) }}</span>
                        <span class="role-card-copy"><strong>{{ $role->name }}</strong><small>{{ $role->users_count }} {{ $role->users_count === 1 ? 'member' : 'members' }}</small></span>
                        <span class="role-card-arrow" aria-hidden="true">›</span>
                    </button>
                @empty
                    <p class="role-empty">No roles have been created yet.</p>
                @endforelse
            </div>
            @can('roles.create')
                <button class="role-add-link" type="button" wire:click="startCreate">＋ Create a new role</button>
            @endcan
        </aside>

        <section class="role-editor" aria-label="Selected role permissions">
            @php
                $protectedRole = $selectedRole && $selectedRole->name === \App\Support\RolePermissionCatalog::ADMIN_ROLE;
                $canEditSelectedRole = $selectedRole && ! $protectedRole && auth()->user()->can('roles.update');
                $canEditPermissions = $canEditSelectedRole || (! $selectedRole && ! $editingId && auth()->user()->can('roles.create'));
            @endphp
            <div class="role-editor-heading">
                <div>
                    <div class="role-title-line"><span class="role-avatar {{ $selectedRole ? 'blue' : 'green' }}">{{ $selectedRole ? mb_substr($selectedRole->name, 0, 1) : '+' }}</span><h2>{{ $selectedRole?->name ?? ($editingId ? 'Create role' : 'New role') }}</h2></div>
                    <p>{{ $protectedRole ? 'Protected system role · permissions are read-only' : ($selectedRole ? 'Review the access granted by this role.' : 'Choose a name and permissions for this access profile.') }}</p>
                </div>
                @if ($selectedRole && ! $protectedRole)
                    @can('roles.create')
                        <button class="role-button role-button-light" type="button" wire:click="duplicateRole">▢ <span>Duplicate role</span></button>
                    @endcan
                @endif
            </div>
            <div class="role-summary">
                <span><strong>{{ count($selectedPermissionNames) }}</strong> permissions selected</span>
                <span class="role-summary-dot"></span>
                <span><strong>{{ $selectedRole?->users_count ?? 0 }}</strong> members assigned</span>
                <span class="role-save-state">Saved to this account</span>
            </div>

            @if (($selectedRole && $canEditSelectedRole) || (! $selectedRole && auth()->user()->can('roles.create')))
                <div class="role-name-field">
                    <label for="role-name">Role name</label>
                    <input id="role-name" type="text" maxlength="255" autocomplete="off" wire:model="name" placeholder="e.g. Content editor">
                    @error('name') <span class="role-field-error">{{ $message }}</span> @enderror
                </div>
            @endif

            <div class="role-tools">
                <label class="role-search"><span aria-hidden="true">⌕</span><input type="search" placeholder="Search modules..." aria-label="Search modules" data-module-search><kbd>⌘ K</kbd></label>
                @if ($canEditPermissions)
                    <div class="role-presets" aria-label="Permission presets">
                        <button type="button" wire:click="applyPreset('full')">Grant full access</button>
                        <button type="button" wire:click="applyPreset('read')">Read-only</button>
                        <button type="button" wire:click="applyPreset('clear')">Clear all</button>
                    </div>
                @endif
            </div>

            <div class="role-matrix-wrap">
                <table class="role-matrix">
                    <thead><tr><th scope="col">Module / feature</th>@foreach ($permissionColumns as $column)<th scope="col">{{ $column['label'] }}</th>@endforeach</tr></thead>
                    <tbody data-permission-rows>
                        @foreach ($permissionMatrix as $module)
                            @php
                                $moduleNames = $module['permissions']->pluck('name')->all();
                                $moduleSelected = count(array_diff($moduleNames, $selectedPermissionNames)) === 0;
                            @endphp
                            <tr data-module-row="{{ $module['module'] }}">
                                <th scope="row">
                                    <div class="role-module-name">
                                        <label class="role-check" title="Select all for {{ $module['module'] }}"><input type="checkbox" aria-label="Select all permissions for {{ $module['module'] }}" @checked($moduleSelected) wire:click="toggleModule('{{ $module['module'] }}')" @disabled(! $canEditPermissions)><span></span></label>
                                        <span><strong>{{ $module['module'] }}</strong><small>{{ count($moduleNames) }} {{ count($moduleNames) === 1 ? 'permission' : 'permissions' }}</small></span>
                                    </div>
                                </th>
                                @foreach ($permissionColumns as $column)
                                    @php($cellPermissions = $module['permissions']->where('column', $column['key']))
                                    <td>
                                        @foreach ($cellPermissions as $permission)
                                            <label class="role-check role-permission-check" title="{{ $permission['label'] }}">
                                                <input type="checkbox" value="{{ $permission['name'] }}" aria-label="{{ $permission['label'] }}" wire:model="selectedPermissionNames" @disabled(! $canEditPermissions)>
                                                <span></span>
                                            </label>
                                        @endforeach
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th scope="row">Select all permissions</th>@foreach ($permissionColumns as $column)
                            @php($columnNames = $permissionMatrix->flatMap(fn ($module) => $module['permissions']->where('column', $column['key'])->pluck('name'))->all())
                            <td><label class="role-check" title="Select all {{ strtolower($column['label']) }} permissions"><input type="checkbox" aria-label="Select all {{ strtolower($column['label']) }} permissions" @checked(count(array_diff($columnNames, $selectedPermissionNames)) === 0) wire:click="toggleColumn('{{ $column['key'] }}')" @disabled(! $canEditPermissions)><span></span></label></td>
                        @endforeach</tr>
                    </tfoot>
                </table>
                <p class="role-empty-filter" data-empty-filter hidden>No modules match your search.</p>
            </div>

            <div class="role-editor-footer">
                <span>Changes are persisted to the application role record.</span>
                <div class="role-editor-actions">
                    @if ($selectedRole && ! $protectedRole)
                        @can('roles.delete')
                            <button class="role-button role-button-danger" type="button" wire:click="delete({{ $selectedRole->id }})" wire:confirm="Delete role {{ $selectedRole->name }}?">Delete role</button>
                        @endcan
                    @endif
                    @if ($canEditPermissions)
                        <button class="role-button role-button-primary" type="button" wire:click="saveSelected" wire:loading.attr="disabled" wire:target="saveSelected">
                            <span class="role-spinner" wire:loading wire:target="saveSelected"></span>
                            <span wire:loading.remove wire:target="saveSelected">{{ $selectedRole ? 'Save changes' : 'Create role' }}</span>
                            <span wire:loading wire:target="saveSelected">Saving…</span>
                        </button>
                    @endif
                </div>
            </div>
        </section>
    </div>

    @can('employees.assign-roles')
        <section class="role-users-view" data-view="users" @if ($activeTab !== 'users') hidden @endif>
            <header class="role-users-heading"><div><h2>Team members</h2><p>Assign one or more existing roles to each employee.</p></div><label class="role-search"><span aria-hidden="true">⌕</span><input type="search" placeholder="Search employees..." aria-label="Search employees" wire:model.live.debounce.250ms="userSearch"></label></header>
            @error('roleIds') <p class="role-feedback role-feedback-error" role="alert">{{ $message }}</p> @enderror
            @error('selectedRoleIds') <p class="role-feedback role-feedback-error" role="alert">{{ $message }}</p> @enderror
            <div class="role-user-table-wrap">
                <table class="role-user-table"><thead><tr><th>Employee</th><th>Assigned roles</th><th>Status</th></tr></thead><tbody>
                    @forelse ($employees as $employee)
                        @php($isAdministrator = $employee->hasRole(\App\Support\RolePermissionCatalog::ADMIN_ROLE))
                        <tr wire:key="role-assignment-{{ $employee->id }}">
                            <td><div class="role-person"><span class="role-person-avatar">{{ mb_substr($employee->username, 0, 2) }}</span><span><strong>{{ $employee->username }}</strong><small>{{ $employee->email }}</small></span></div></td>
                            <td>
                                <details class="role-assignment-dropdown">
                                    <summary>{{ $employee->roles->count() }} {{ $employee->roles->count() === 1 ? 'role' : 'roles' }} · Manage</summary>
                                    <div class="role-assignment-options">
                                        @if ($isAdministrator)
                                            <span class="role-assignment-protected">System Administrator · protected</span>
                                        @endif
                                        @forelse ($assignableRoles as $role)
                                            <label><input type="checkbox" value="{{ $role->id }}" wire:model="employeeRoleSelections.{{ $employee->id }}"> <span>{{ $role->name }}</span></label>
                                        @empty
                                            <span class="role-empty">No assignable roles.</span>
                                        @endforelse
                                        <button class="role-button role-button-primary" type="button" wire:click="assignEmployeeRoles({{ $employee->id }})" wire:loading.attr="disabled" wire:target="assignEmployeeRoles({{ $employee->id }})">Save assignment</button>
                                    </div>
                                </details>
                            </td>
                            <td><span class="role-user-status active">Active</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="role-empty">No active employees match this search.</td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </section>
    @endcan
</section>
