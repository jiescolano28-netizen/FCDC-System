<section class="role-setup" aria-labelledby="roles-heading" data-role-setup>
    <header class="role-header">
        <div class="role-header-copy">
            <p class="role-eyebrow">ACCESS CONTROL <span aria-hidden="true">·</span> ADMINISTRATION</p>
            <h1 id="roles-heading">Roles &amp; permissions</h1>
            <p class="role-subtitle">Define what each role can see and do, then assign roles to your team.</p>
        </div>
        <div class="role-header-stats" aria-label="Access control summary">
            <div class="role-stat">
                <span class="role-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M11 12 20 3M17.5 5.5l2 2M15 8l2 2"/></svg></span>
                <span class="role-stat-copy"><strong>{{ $roles->count() }}</strong><small>{{ $roles->count() === 1 ? 'role defined' : 'roles defined' }}</small></span>
            </div>
            <div class="role-stat">
                <span class="role-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20v-1.6a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20M9 10.4a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 20v-1.6a4 4 0 0 0-3-3.86M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <span class="role-stat-copy"><strong>{{ $roles->sum('users_count') }}</strong><small>{{ $roles->sum('users_count') === 1 ? 'role assignment' : 'role assignments' }}</small></span>
            </div>
        </div>
    </header>

    @if ($errors->has('roles') || $errors->has('name'))
        <p class="role-alert" role="alert"><span aria-hidden="true">⚠</span>{{ $errors->first('roles') ?: $errors->first('name') }}</p>
    @endif

    <nav class="role-tabs" aria-label="Role setup views">
        <button class="role-tab {{ $activeTab === 'permissions' ? 'active' : '' }}" type="button" wire:click="setActiveTab('permissions')" aria-pressed="{{ $activeTab === 'permissions' ? 'true' : 'false' }}">Permissions</button>
        @can('employees.assign-roles')
            <button class="role-tab {{ $activeTab === 'users' ? 'active' : '' }}" type="button" wire:click="setActiveTab('users')" aria-pressed="{{ $activeTab === 'users' ? 'true' : 'false' }}">User assignments <span class="role-tab-count">{{ $employees->count() }}</span></button>
        @endcan
    </nav>

    <div class="role-layout" data-view="permissions" @if ($activeTab !== 'permissions') hidden @endif>
        <aside class="role-list-panel" aria-label="Roles">
            <div class="role-list-heading"><div><h2>Roles</h2><span>Access profiles</span></div><span class="role-count">{{ $roles->count() }}</span></div>

            @if ($roles->count() > 4)
                <label class="role-list-search">
                    <span aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></span>
                    <input type="search" placeholder="Find a role" aria-label="Find a role" wire:model.live.debounce.250ms="roleSearch">
                </label>
            @endif

            <div class="role-cards">
                @forelse ($roleOptions as $role)
                    @php
                        $isProtectedRole = $role->name === \App\Support\RolePermissionCatalog::ADMIN_ROLE;
                    @endphp
                    <button type="button" class="role-card {{ $role->id === $selectedRoleId ? 'selected' : '' }}" wire:click="selectRole({{ $role->id }})" aria-pressed="{{ $role->id === $selectedRoleId ? 'true' : 'false' }}">
                        <span class="role-card-icon {{ ['violet', 'blue', 'green', 'amber'][$loop->index % 4] }}">{{ mb_substr($role->name, 0, 1) }}</span>
                        <span class="role-card-copy">
                            <strong>{{ $role->name }}</strong>
                            <small>{{ $role->users_count }} {{ $role->users_count === 1 ? 'member' : 'members' }} <span aria-hidden="true">·</span> {{ $role->permissions->count() }} {{ $role->permissions->count() === 1 ? 'permission' : 'permissions' }}</small>
                        </span>
                        @if ($isProtectedRole)
                            <span class="role-card-tag">System</span>
                        @endif
                        <span class="role-card-arrow" aria-hidden="true">›</span>
                    </button>
                @empty
                    <p class="role-empty">{{ $roleSearch === '' ? 'No roles have been created yet.' : 'No roles match your search.' }}</p>
                @endforelse
            </div>

            @can('roles.create')
                <button class="role-add-link" type="button" wire:click="startCreate"><span aria-hidden="true">＋</span> Create a new role</button>
            @endcan
        </aside>

        <section class="role-editor" data-role-editor aria-label="Selected role permissions">
            @php
                $protectedRole = $selectedRole && $selectedRole->name === \App\Support\RolePermissionCatalog::ADMIN_ROLE;
                $canEditSelectedRole = $selectedRole && ! $protectedRole && auth()->user()->can('roles.update');
                $canEditPermissions = $canEditSelectedRole || (! $selectedRole && ! $editingId && auth()->user()->can('roles.create'));
            @endphp
            <div class="role-editor-heading">
                <div class="role-title-line">
                    <span class="role-avatar {{ $selectedRole ? 'blue' : 'green' }}">{{ $selectedRole ? mb_substr($selectedRole->name, 0, 1) : '+' }}</span>
                    <div>
                        <h2>{{ $selectedRole?->name ?? ($editingId ? 'Create role' : 'New role') }}</h2>
                        <p>{{ $protectedRole ? 'Protected system role · permissions are read-only.' : ($selectedRole ? 'Review and adjust the access granted by this role.' : 'Choose a name and permissions for this access profile.') }}</p>
                    </div>
                </div>
                @if ($selectedRole && ! $protectedRole)
                    @can('roles.create')
                        <button class="role-button role-button-light" type="button" wire:click="duplicateRole"><span aria-hidden="true"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2.5"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></span> <span>Duplicate role</span></button>
                    @endcan
                @endif
            </div>
            <div class="role-summary">
                <span class="role-summary-item"><strong>{{ count($selectedPermissionNames) }}</strong> of {{ $permissionCount }} permissions</span>
                <span class="role-summary-sep" aria-hidden="true"></span>
                <span class="role-summary-item"><strong>{{ $selectedRole?->users_count ?? 0 }}</strong> {{ ($selectedRole?->users_count ?? 0) === 1 ? 'member' : 'members' }} assigned</span>
                @if ($protectedRole)
                    <span class="role-summary-badge">Read-only</span>
                @endif
                <span class="role-dirty-flag" data-role-dirty><span aria-hidden="true"></span> Unsaved changes</span>
            </div>

            @if (($selectedRole && $canEditSelectedRole) || (! $selectedRole && auth()->user()->can('roles.create')))
                <div class="role-name-field">
                    <label for="role-name">Role name</label>
                    <input id="role-name" type="text" maxlength="255" autocomplete="off" wire:model="name" placeholder="e.g. Inventory clerk">
                    @error('name') <span class="role-field-error">{{ $message }}</span> @enderror
                </div>
            @endif

            <div class="role-tools">
                <label class="role-search"><span aria-hidden="true"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></span><input type="search" placeholder="Search modules or permissions" aria-label="Search modules or permissions" data-module-search><kbd>Ctrl K</kbd></label>
                @if ($canEditPermissions)
                    <div class="role-presets" role="group" aria-label="Permission presets">
                        <button type="button" wire:click="applyPreset('full')">Full access</button>
                        <button type="button" wire:click="applyPreset('read')">Read-only</button>
                        <button type="button" wire:click="applyPreset('clear')">Clear all</button>
                    </div>
                @endif
            </div>

            <div class="role-matrix-wrap">
                <table class="role-matrix">
                    <thead>
                        <tr>
                            <th scope="col"><div class="role-col-heading"><span>Module / feature</span></div></th>
                            @foreach ($permissionColumns as $column)
                                @php
                                    $columnNames = $permissionMatrix->flatMap(fn ($module) => $module['permissions']->where('column', $column['key'])->pluck('name'))->all();
                                @endphp
                                <th scope="col" class="{{ $column['key'] === 'other' ? 'role-col-other' : '' }}">
                                    <div class="role-col-heading">
                                        <span>{{ $column['label'] }}</span>
                                        <label class="role-check role-check-column" title="Toggle all {{ strtolower($column['label']) }} permissions">
                                            <input type="checkbox" aria-label="Toggle all {{ strtolower($column['label']) }} permissions" @checked(count(array_diff($columnNames, $selectedPermissionNames)) === 0) wire:click="toggleColumn('{{ $column['key'] }}')" @disabled(! $canEditPermissions)>
                                            <span></span>
                                        </label>
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody data-permission-rows>
                        @foreach ($permissionMatrix as $module)
                            @php
                                $moduleNames = $module['permissions']->pluck('name')->all();
                                $moduleSelectedCount = count(array_intersect($moduleNames, $selectedPermissionNames));
                                $moduleTotal = count($moduleNames);
                                $moduleSelected = $moduleSelectedCount === $moduleTotal;
                            @endphp
                            <tr data-module-row="{{ $module['module'] }}" data-module-keywords="{{ mb_strtolower($module['module'].' '.$module['permissions']->pluck('label')->implode(' ')) }}">
                                <th scope="row">
                                    <div class="role-module-name">
                                        <label class="role-check" title="Select all for {{ $module['module'] }}"><input type="checkbox" aria-label="Select all permissions for {{ $module['module'] }}" @checked($moduleSelected) wire:click="toggleModule('{{ $module['module'] }}')" @disabled(! $canEditPermissions)><span></span></label>
                                        <span class="role-module-copy">
                                            <strong>{{ $module['module'] }}</strong>
                                            <small>
                                                <span class="role-module-meter" aria-hidden="true"><i style="width: {{ $moduleTotal > 0 ? (int) round($moduleSelectedCount / $moduleTotal * 100) : 0 }}%"></i></span>
                                                {{ $moduleSelectedCount }} of {{ $moduleTotal }} selected
                                            </small>
                                        </span>
                                    </div>
                                </th>
                                @foreach ($permissionColumns as $column)
                                    @php($cellPermissions = $module['permissions']->where('column', $column['key']))
                                    <td class="{{ $column['key'] === 'other' ? 'role-col-other' : '' }}">
                                        @forelse ($cellPermissions as $permission)
                                            <label class="role-check role-permission-check" title="{{ $permission['label'] }}">
                                                <input type="checkbox" value="{{ $permission['name'] }}" aria-label="{{ $permission['label'] }}" wire:model="selectedPermissionNames" @disabled(! $canEditPermissions)>
                                                <span></span>
                                            </label>
                                        @empty
                                            <span class="role-cell-empty" aria-hidden="true">–</span>
                                        @endforelse
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="role-empty-filter" data-empty-filter hidden>No modules match your search.</p>
            </div>

            <div class="role-editor-footer">
                <span class="role-footer-note">{{ $canEditPermissions ? 'Your changes are saved to this role only when you save.' : 'You have read-only access to this role.' }}</span>
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
            <header class="role-users-heading"><div><h2>Team members</h2><p>Assign one or more roles to each employee.</p></div><label class="role-search"><span aria-hidden="true"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></span><input type="search" placeholder="Search employees..." aria-label="Search employees" wire:model.live.debounce.250ms="userSearch"></label></header>
            @error('roleIds') <p class="role-alert" role="alert"><span aria-hidden="true">⚠</span>{{ $message }}</p> @enderror
            @error('selectedRoleIds') <p class="role-alert" role="alert"><span aria-hidden="true">⚠</span>{{ $message }}</p> @enderror
            <div class="role-user-table-wrap">
                <table class="role-user-table">
                    <thead><tr><th scope="col">Employee</th><th scope="col">Assigned roles</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            @php($isAdministrator = $employee->hasRole(\App\Support\RolePermissionCatalog::ADMIN_ROLE))
                            <tr wire:key="role-assignment-{{ $employee->id }}">
                                <td><div class="role-person"><span class="role-person-avatar" aria-hidden="true">{{ mb_substr($employee->username, 0, 2) }}</span><span class="role-person-copy"><strong>{{ $employee->username }}</strong><small>{{ $employee->email }}</small></span></div></td>
                                <td>
                                    <details class="role-assignment">
                                        <summary>
                                            <span class="role-assignment-chips">
                                                @forelse ($employee->roles as $role)
                                                    <span class="role-chip {{ $role->name === \App\Support\RolePermissionCatalog::ADMIN_ROLE ? 'role-chip-system' : '' }}">{{ $role->name }}</span>
                                                @empty
                                                    <span class="role-chip role-chip-empty">No roles</span>
                                                @endforelse
                                            </span>
                                            <span class="role-assignment-toggle">Manage<span aria-hidden="true"> ▾</span></span>
                                        </summary>
                                        <div class="role-assignment-options">
                                            @if ($isAdministrator)
                                                <p class="role-assignment-protected"><span aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10.5" rx="2.4"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg></span> System Administrator is protected and always stays assigned.</p>
                                            @endif
                                            <div class="role-assignment-list">
                                                @forelse ($assignableRoles as $role)
                                                    <label class="role-assignment-option"><input type="checkbox" value="{{ $role->id }}" wire:model="employeeRoleSelections.{{ $employee->id }}"> <span>{{ $role->name }}</span></label>
                                                @empty
                                                    <p class="role-empty">No assignable roles yet. Create a role first.</p>
                                                @endforelse
                                            </div>
                                            <div class="role-assignment-actions">
                                                <button class="role-button role-button-primary" type="button" wire:click="assignEmployeeRoles({{ $employee->id }})" wire:loading.attr="disabled" wire:target="assignEmployeeRoles({{ $employee->id }})">Save assignment</button>
                                            </div>
                                        </div>
                                    </details>
                                </td>
                                <td><span class="role-user-status active">Active</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="role-empty">No active employees match this search.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endcan
</section>
