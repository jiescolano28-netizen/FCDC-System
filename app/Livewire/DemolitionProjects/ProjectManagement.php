<?php

namespace App\Livewire\DemolitionProjects;

use App\Models\DemolitionProject;
use App\Models\Inventory;
use App\Models\RecoveredMaterial;
use App\Services\Inventory\AssessRecoveredMaterial;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ProjectManagement extends Component
{
    public ?int $editingId = null;
    public string $name = '';
    public string $location = '';
    public string $startDate = '';
    public string $endDate = '';

    public ?int $recoveryProjectId = null;
    public string $recoveryMaterial = '';
    public string $recoveryQuantity = '';
    public string $recoveryUnit = '';
    public string $recoveryCondition = 'good';
    public string $recoveryNotes = '';
    public ?int $assessmentRecoveryId = null;
    public bool $correctingAssessment = false;
    public string $acceptedQuantity = '';
    public string $rejectedQuantity = '';
    public string $rejectionReason = '';
    public string $inventoryChoice = 'existing';
    public ?int $inventoryId = null;
    public string $newInventoryName = '';
    public string $newInventoryCategory = '';
    public string $recoveryUnitValue = '';


    public function recordRecovery(): void
    {
        $this->authorizePermission('demolition-projects.manage');
        $validated = $this->validate([
            'recoveryProjectId' => ['required', 'integer', 'exists:demolition_projects,id'],
            'recoveryMaterial' => ['required', 'string', 'max:255'],
            'recoveryQuantity' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'recoveryUnit' => ['required', 'string', 'max:50'],
            'recoveryCondition' => ['required', 'in:good,fair,poor'],
            'recoveryNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        RecoveredMaterial::create([
            'demolition_project_id' => $validated['recoveryProjectId'],
            'recorded_by' => auth()->id(),
            'material' => $validated['recoveryMaterial'],
            'quantity' => $validated['recoveryQuantity'],
            'unit' => $validated['recoveryUnit'],
            'condition' => $validated['recoveryCondition'],
            'notes' => $validated['recoveryNotes'] ?: null,
        ]);

        $this->reset(['recoveryMaterial', 'recoveryQuantity', 'recoveryUnit', 'recoveryNotes']);
        $this->recoveryCondition = 'good';
        $this->resetValidation();
    }

    public function beginAssessment(int $id, bool $correction = false): void
    {
        $this->authorizePermission('demolition-projects.manage');
        $recovery = RecoveredMaterial::query()->with('latestAssessment')->findOrFail($id);
        abort_unless((bool) $recovery->latestAssessment === $correction, 404);

        $prior = $recovery->latestAssessment;
        $this->assessmentRecoveryId = $id;
        $this->correctingAssessment = $correction;
        $this->acceptedQuantity = $prior?->accepted_quantity ?? $recovery->quantity;
        $this->rejectedQuantity = $prior?->rejected_quantity ?? '0.00';
        $this->rejectionReason = $prior?->rejection_reason ?? '';
        $this->recoveryUnitValue = $prior?->assigned_unit_value_cents !== null
            ? number_format($prior->assigned_unit_value_cents / 100, 2, '.', '')
            : '';
        $this->inventoryId = $prior?->inventory_id;
        $this->inventoryChoice = 'existing';
        $this->resetValidation();
    }

    public function saveAssessment(): void
    {
        $this->authorizePermission('demolition-projects.manage');
        $recovery = RecoveredMaterial::query()->with('latestAssessment')->findOrFail($this->assessmentRecoveryId);
        abort_unless((bool) $recovery->latestAssessment === $this->correctingAssessment, 404);

        $validated = $this->validate([
            'assessmentRecoveryId' => ['required', 'integer', 'exists:recovered_materials,id'],
            'acceptedQuantity' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'rejectedQuantity' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'rejectionReason' => ['nullable', 'string', 'max:255'],
            'inventoryChoice' => ['required', 'in:existing,new'],
            'inventoryId' => ['required_if:inventoryChoice,existing', 'nullable', 'integer', 'exists:inventories,id'],
            'newInventoryName' => ['required_if:inventoryChoice,new', 'nullable', 'string', 'max:255'],
            'newInventoryCategory' => ['required_if:inventoryChoice,new', 'nullable', 'string', 'max:255'],
            'recoveryUnitValue' => ['nullable', 'numeric', 'min:0.01', 'decimal:0,2'],
        ]);

        $acceptedCents = (int) round((float) $validated['acceptedQuantity'] * 100);
        $rejectedCents = (int) round((float) $validated['rejectedQuantity'] * 100);
        $recoveredCents = (int) round((float) $recovery->quantity * 100);
        if ($acceptedCents + $rejectedCents !== $recoveredCents) {
            throw ValidationException::withMessages(['acceptedQuantity' => 'Accepted and rejected quantities must equal the recovered quantity.']);
        }
        if ($rejectedCents > 0 && trim($validated['rejectionReason'] ?? '') === '') {
            throw ValidationException::withMessages(['rejectionReason' => 'A reason is required for rejected material.']);
        }

        app(AssessRecoveredMaterial::class)->handle($recovery->id, [
            'accepted_quantity' => $validated['acceptedQuantity'],
            'rejected_quantity' => $validated['rejectedQuantity'],
            'rejection_reason' => $validated['rejectionReason'] ?: null,
            'assigned_unit_value' => $validated['recoveryUnitValue'] ?? null,
            'create_inventory' => $validated['inventoryChoice'] === 'new',
            'inventory_id' => $validated['inventoryId'],
            'new_item_name' => $validated['newInventoryName'],
            'new_item_category' => $validated['newInventoryCategory'],
        ], (int) auth()->id(), $this->correctingAssessment);

        $this->cancelAssessment();
    }

    public function cancelAssessment(): void
    {
        $this->reset([
            'assessmentRecoveryId', 'correctingAssessment', 'acceptedQuantity', 'rejectedQuantity',
            'rejectionReason', 'recoveryUnitValue', 'inventoryChoice', 'inventoryId', 'newInventoryName',
            'newInventoryCategory',
        ]);
        $this->inventoryChoice = 'existing';
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorizePermission('demolition-projects.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'startDate' => ['required', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
        ]);

        $project = $this->editingId
            ? DemolitionProject::findOrFail($this->editingId)
            : new DemolitionProject;

        $project->fill([
            'name' => $validated['name'],
            'location' => $validated['location'],
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'] ?: null,
        ])->save();

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $this->authorizePermission('demolition-projects.manage');
        $project = DemolitionProject::findOrFail($id);

        $this->editingId = $project->id;
        $this->name = $project->name;
        $this->location = $project->location;
        $this->startDate = $project->start_date->toDateString();
        $this->endDate = $project->end_date?->toDateString() ?? '';
        $this->resetValidation();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'location', 'startDate', 'endDate']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.demolition-projects.project-management', [
            'projects' => DemolitionProject::query()
                ->with(['recoveredMaterials.latestAssessment.inventory', 'recoveredMaterials.assessments.inventory', 'recoveredMaterials.assessments.stockMovement.reversal', 'recoveredMaterials.assessments.stockMovement.accountingJournal', 'recoveredMaterials.assessments.stockMovement.valuationCorrections.accountingJournal', 'recoveredMaterials.assessments.valuationCounterpart', 'recoveredMaterials.assessments.valuationApprover'])
                ->orderByDesc('id')
                ->get(),
            'activeItems' => Inventory::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name', 'unit']),
            'recoveryCounterpart' => \App\Models\AccountingPostingMapping::query()
                ->where('source', 'recovery_offset')->with('account')->first(),
        ])->layout('layouts.app', ['title' => 'Demolition Projects']);
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
