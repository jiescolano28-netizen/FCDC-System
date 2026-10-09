<section class="inventory-page demolition-project-page">
    <header class="columns" style="justify-content:space-between; margin-bottom:18px;">
        <div>
            <h1>Demolition Projects</h1>
            <p class="muted">Maintain source records used to trace recovered materials to their originating site.</p>
        </div>
        <span>{{ $projects->count() }} project(s)</span>
    </header>

    @can('demolition-projects.manage')
        <div class="panel">
            <h2>{{ $editingId ? 'Edit project' : 'Add a project' }}</h2>
            <form wire:submit="save">
                <div class="fields">
                    <div>
                        <label for="projectName">Project name</label>
                        <input id="projectName" wire:model="name" autocomplete="organization">
                        @error('name') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="projectLocation">Site / location</label>
                        <input id="projectLocation" wire:model="location" autocomplete="street-address">
                        @error('location') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="projectStartDate">Start date</label>
                        <input id="projectStartDate" type="date" wire:model="startDate">
                        @error('startDate') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="projectEndDate">End date (optional)</label>
                        <input id="projectEndDate" type="date" wire:model="endDate">
                        @error('endDate') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="actions" style="margin-top:14px;">
                    <button class="primary" type="submit">{{ $editingId ? 'Save project' : 'Add project' }}</button>
                    @if ($editingId)
                        <button class="secondary" type="button" wire:click="resetForm">Cancel</button>
                    @endif
                </div>
            </form>
        </div>
    @endcan

    @can('demolition-projects.manage')
        <div class="panel">
            <h2>Record recovered material</h2>
            <form wire:submit="recordRecovery">
                <div class="fields">
                    <div>
                        <label for="recoveryProjectId">Demolition project</label>
                        <select id="recoveryProjectId" wire:model="recoveryProjectId">
                            <option value="">Select project</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->code }} · {{ $project->name }}</option>
                            @endforeach
                        </select>
                        @error('recoveryProjectId') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="recoveryMaterial">Material</label>
                        <input id="recoveryMaterial" wire:model="recoveryMaterial">
                        @error('recoveryMaterial') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="recoveryQuantity">Recovered quantity</label>
                        <input id="recoveryQuantity" type="number" min="0.01" step="0.01" wire:model="recoveryQuantity">
                        @error('recoveryQuantity') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="recoveryUnit">Unit</label>
                        <input id="recoveryUnit" maxlength="50" wire:model="recoveryUnit" placeholder="e.g. piece, kg">
                        @error('recoveryUnit') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="recoveryCondition">Condition</label>
                        <select id="recoveryCondition" wire:model="recoveryCondition">
                            <option value="good">Good</option>
                            <option value="fair">Fair</option>
                            <option value="poor">Poor</option>
                        </select>
                        @error('recoveryCondition') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="recoveryNotes">Notes (optional)</label>
                        <input id="recoveryNotes" wire:model="recoveryNotes">
                        @error('recoveryNotes') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <button class="primary" type="submit" style="margin-top:14px;">Save recovered material</button>
            </form>
        </div>
    @endcan

    <div class="panel">
        <h2>Recovered materials by project</h2>
        @forelse ($projects as $project)
            <section style="margin-top:18px;">
                <h3>{{ $project->code }} · {{ $project->name }}</h3>
                @forelse ($project->recoveredMaterials as $recovery)
                    @php($assessment = $recovery->latestAssessment)
                    <div style="padding:12px 0; border-top:1px solid var(--border, #ddd);">
                        <strong>{{ $recovery->material }}</strong> · {{ number_format((float) $recovery->quantity, 2) }} {{ $recovery->unit }}
                        · {{ ucfirst($recovery->condition) }} condition
                        @if ($recovery->notes) · {{ $recovery->notes }} @endif
                        @if ($assessment)
                            <div class="muted" style="margin:6px 0;">
                                @foreach ($recovery->assessments as $history)
                                    <p>
                                        Assessment #{{ $history->id }}:
                                        accepted {{ number_format((float) $history->accepted_quantity, 2) }},
                                        rejected {{ number_format((float) $history->rejected_quantity, 2) }}
                                        @if ($history->rejection_reason) · {{ $history->rejection_reason }} @endif
                                        · Inventory: {{ $history->inventory->name }}
                                        @if ($history->stockMovement)
                                            · stock movement #{{ $history->stockMovement->id }}
                                            @if ($history->stockMovement->reversal) · reversed by #{{ $history->stockMovement->reversal->id }} @endif
                                        @endif
                                        @if ($history->supersedes_assessment_id) · reassesses #{{ $history->supersedes_assessment_id }} @endif
                                    </p>
                                @endforeach
                            </div>
                        @else
                            <p class="muted" style="margin:6px 0;">Not assessed</p>
                        @endif
                        @can('demolition-projects.manage')
                            @if ($assessment)
                                <button class="secondary" type="button" wire:click="beginAssessment({{ $recovery->id }}, true)">Reverse and reassess</button>
                            @else
                                <button class="primary" type="button" wire:click="beginAssessment({{ $recovery->id }})">Assess and receive</button>
                            @endif
                        @endcan
                    </div>
                @empty
                    <p class="muted">No material has been recorded for this project.</p>
                @endforelse
            </section>
        @empty
            <p class="muted">Create a demolition project before recording recovered materials.</p>
        @endforelse
    </div>

    @if ($assessmentRecoveryId)
        @php($selectedRecovery = \App\Models\RecoveredMaterial::find($assessmentRecoveryId))
        <div class="panel">
            <h2>{{ $correctingAssessment ? 'Correct and reassess' : 'Assess recovered material' }}: {{ $selectedRecovery?->material }}</h2>
            <p class="muted">Recovered: {{ number_format((float) $selectedRecovery?->quantity, 2) }} {{ $selectedRecovery?->unit }}. Accepted and rejected quantities must reconcile exactly.</p>
            <form wire:submit="saveAssessment">
                <div class="fields">
                    <div>
                        <label for="acceptedQuantity">Accepted quantity</label>
                        <input id="acceptedQuantity" type="number" min="0" step="0.01" wire:model="acceptedQuantity">
                        @error('acceptedQuantity') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="rejectedQuantity">Rejected quantity</label>
                        <input id="rejectedQuantity" type="number" min="0" step="0.01" wire:model="rejectedQuantity">
                        @error('rejectedQuantity') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="rejectionReason">Rejection reason (required when rejecting material)</label>
                        <input id="rejectionReason" wire:model="rejectionReason">
                        @error('rejectionReason') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <fieldset>
                        <legend>Inventory item (unit must match: {{ $selectedRecovery?->unit }})</legend>
                        <label><input type="radio" value="existing" wire:model.live="inventoryChoice"> Existing item</label>
                        <label><input type="radio" value="new" wire:model.live="inventoryChoice"> Create new item</label>
                    </fieldset>
                    @if ($inventoryChoice === 'existing')
                        <div>
                            <label for="inventoryId">Active inventory item</label>
                            <select id="inventoryId" wire:model="inventoryId">
                                <option value="">Select matching unit item</option>
                                @foreach ($activeItems->where('unit', $selectedRecovery?->unit) as $item)
                                    <option value="{{ $item->id }}">{{ $item->code }} · {{ $item->name }} ({{ $item->unit }})</option>
                                @endforeach
                            </select>
                            @error('inventoryId') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    @else
                        <div>
                            <label for="newInventoryName">New inventory item name</label>
                            <input id="newInventoryName" wire:model="newInventoryName">
                            @error('newInventoryName') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="newInventoryCategory">Category</label>
                            <input id="newInventoryCategory" wire:model="newInventoryCategory">
                            @error('newInventoryCategory') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="newInventoryUnitCost">Unit cost</label>
                            <input id="newInventoryUnitCost" type="number" min="0" step="0.01" wire:model="newInventoryUnitCost">
                            @error('newInventoryUnitCost') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    @endif
                </div>
                @error('assessment') <span class="error">{{ $message }}</span> @enderror
                <div class="actions" style="margin-top:14px;">
                    <button class="primary" type="submit">{{ $correctingAssessment ? 'Reverse and post reassessment' : 'Post assessment and receipt' }}</button>
                    <button class="secondary" type="button" wire:click="cancelAssessment">Cancel</button>
                </div>
            </form>
        </div>
    @endif

    <div class="panel table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Project code</th>
                    <th>Project name</th>
                    <th>Site / location</th>
                    <th>Start date</th>
                    <th>End date</th>
                    @can('demolition-projects.manage') <th>Actions</th> @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($projects as $project)
                    <tr wire:key="demolition-project-{{ $project->id }}">
                        <td><strong>{{ $project->code }}</strong></td>
                        <td>{{ $project->name }}</td>
                        <td>{{ $project->location }}</td>
                        <td>{{ $project->start_date->format('Y-m-d') }}</td>
                        <td>{{ $project->end_date?->format('Y-m-d') ?? '—' }}</td>
                        @can('demolition-projects.manage')
                            <td><button class="secondary" type="button" wire:click="edit({{ $project->id }})">Edit</button></td>
                        @endcan
                    </tr>
                @empty
                    <tr><td colspan="{{ auth()->user()->can('demolition-projects.manage') ? 6 : 5 }}" class="muted">No Demolition Projects have been recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
