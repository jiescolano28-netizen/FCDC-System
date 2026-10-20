<?php

namespace App\Livewire\Inventory;

use App\Models\DemolitionProject;
use App\Models\ReworkingCostRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Reworking Cost Record module.
 *
 * Records and attachment references are held in the signed-in employee's
 * session and mirrored to a source-keyed table so the VAT compliance screens
 * can reconcile the saved input VAT without duplicate entries. This module
 * does not perform inventory revaluation or accounting postings.
 */
class ReworkingCostRecordManagement extends Component
{
    use WithFileUploads;

    public string $search = '';

    public ?int $editingId = null;

    public ?int $projectId = null;

    public string $projectName = '';

    public string $batchNumber = '';

    public string $recordDate = '';

    public string $description = '';

    public string $totalReworkingCost = '';

    public string $inputVat = '0.00';

    /**
     * New supporting document staged for the record being created or edited.
     */
    public $attachmentFile = null;

    /**
     * Attachment already stored on the record being edited, if any.
     *
     * @var array{name: string, size: int, filename: string}|null
     */
    public ?array $existingAttachment = null;

    public bool $removeExistingAttachment = false;

    public string $statusMessage = '';

    public function mount(): void
    {
        $this->recordDate = now()->toDateString();
    }

    public function create(): void
    {
        $this->authorizePermission('inventory.create');
        $this->resetForm();
        $this->statusMessage = '';
        $this->dispatch('reworking-cost-record-form-open');
    }

    public function edit(int $id): void
    {
        $this->authorizePermission('inventory.update');

        $record = collect($this->records())->firstWhere('id', $id);

        if (! $record) {
            return;
        }

        $this->editingId = $record['id'];
        $this->projectId = $record['project_id'] ?? null;
        $this->projectName = $record['project_id'] ? '' : $record['project'];
        $this->batchNumber = $record['batch_number'];
        $this->recordDate = $record['date'];
        $this->description = $record['description'];
        $this->totalReworkingCost = $record['total_reworking_cost'];
        $this->inputVat = $record['input_vat'];
        $this->existingAttachment = $record['attachment'] ?? null;
        $this->removeExistingAttachment = false;
        $this->attachmentFile = null;
        $this->statusMessage = '';
        $this->resetValidation();
        $this->dispatch('reworking-cost-record-form-open');
    }

    public function save(): void
    {
        $this->authorizePermission($this->editingId ? 'inventory.update' : 'inventory.create');

        $validated = $this->validate($this->rules());

        $projectId = null;
        $projectLabel = trim($this->projectName);

        if ($this->projectsExist()) {
            $project = DemolitionProject::findOrFail($validated['projectId']);
            $projectId = $project->id;
            $projectLabel = $project->code.' · '.$project->name;
        }

        $attachment = $this->existingAttachment;

        if ($this->removeExistingAttachment) {
            $attachment = null;
        }

        if ($this->attachmentFile) {
            $attachment = [
                'name' => $this->attachmentFile->getClientOriginalName(),
                'size' => (int) $this->attachmentFile->getSize(),
                'filename' => $this->attachmentFile->getFilename(),
            ];
        }

        $records = $this->records();
        $wasEditing = $this->editingId !== null;

        $record = [
            'id' => $this->editingId ?? $this->nextId($records),
            'project_id' => $projectId,
            'project' => $projectLabel,
            'batch_number' => trim($validated['batchNumber']),
            'date' => $validated['recordDate'],
            'description' => trim($validated['description']),
            'total_reworking_cost' => number_format((float) $validated['totalReworkingCost'], 2, '.', ''),
            'input_vat' => number_format((float) ($validated['inputVat'] ?? 0), 2, '.', ''),
            'attachment' => $attachment,
            'updated_at' => now()->toDateTimeString(),
        ];

        if ($wasEditing) {
            $records = array_map(
                fn (array $existing): array => $existing['id'] === $record['id'] ? $record : $existing,
                $records,
            );
        } else {
            $records[] = $record;
        }

        $this->saveRecords($records);
        $this->persistRecord($record);
        $this->resetForm();
        $this->statusMessage = $wasEditing
            ? 'Reworking cost record updated.'
            : 'Reworking cost record added.';
        $this->dispatch('reworking-cost-record-form-close');
    }

    public function delete(int $id): void
    {
        $this->authorizePermission('inventory.delete');

        $records = collect($this->records())->reject(fn (array $record): bool => $record['id'] === $id)->values()->all();
        $this->saveRecords($records);
        $this->removePersistedRecord($id);

        if ($this->editingId === $id) {
            $this->resetForm();
            $this->dispatch('reworking-cost-record-form-close');
        }

        $this->statusMessage = 'Reworking cost record deleted.';
    }

    public function updatedAttachmentFile(): void
    {
        $this->validateOnly('attachmentFile', [
            'attachmentFile' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId',
            'projectId',
            'projectName',
            'batchNumber',
            'description',
            'totalReworkingCost',
            'attachmentFile',
            'existingAttachment',
            'removeExistingAttachment',
        ]);

        $this->recordDate = now()->toDateString();
        $this->inputVat = '0.00';
        $this->resetValidation();
    }

    public function render()
    {
        $records = collect($this->records())
            ->when(trim($this->search) !== '', function ($records) {
                $search = Str::lower(trim($this->search));

                return $records->filter(fn (array $record): bool => Str::contains(
                    Str::lower(implode(' ', [
                        $record['project'],
                        $record['batch_number'],
                        $record['description'],
                        $record['date'],
                    ])),
                    $search,
                ));
            })
            ->sortByDesc(fn (array $record): string => $record['date'].'-'.str_pad((string) $record['id'], 10, '0', STR_PAD_LEFT))
            ->values();

        $attachmentUrls = $records->mapWithKeys(fn (array $record): array => [
            $record['id'] => $this->attachmentUrl($record['attachment'] ?? null),
        ]);

        $summary = [
            'count' => $records->count(),
            'total' => $records->sum(fn (array $record): float => (float) $record['total_reworking_cost']),
            'vat' => $records->sum(fn (array $record): float => (float) $record['input_vat']),
        ];
        $summary['total_with_vat'] = $summary['total'] + $summary['vat'];

        return view('livewire.inventory.reworking-cost-record-management', [
            'records' => $records,
            'attachmentUrls' => $attachmentUrls,
            'summary' => $summary,
            'projects' => DemolitionProject::query()->orderBy('name')->get(['id', 'code', 'name']),
        ])->layout('layouts.app', ['title' => 'Reworking cost record']);
    }

    protected function rules(): array
    {
        $rules = [
            'batchNumber' => ['required', 'string', 'max:100'],
            'recordDate' => ['required', 'date'],
            'description' => ['required', 'string', 'max:2000'],
            'totalReworkingCost' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'inputVat' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'attachmentFile' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];

        if ($this->projectsExist()) {
            $rules['projectId'] = ['required', 'integer', 'exists:demolition_projects,id'];
        } else {
            $rules['projectName'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * Saved records for the signed-in employee.
     *
     * The source-keyed table is the durable copy used by VAT compliance. The
     * session copy is retained for records created before the table existed
     * and for the same-request interface state. Merging by source record id
     * keeps both copies from being shown twice.
     *
     * @return array<int, array<string, mixed>>
     */
    private function records(): array
    {
        $records = $this->databaseRecords()->keyBy('id');

        foreach ($this->sessionRecords() as $record) {
            if (! is_array($record) || ! isset($record['id'])) {
                continue;
            }

            // The table is authoritative. Session data is only a fallback for
            // records saved before the table was available.
            $id = (int) $record['id'];

            if (! $records->has($id)) {
                $records->put($id, $record);
            }
        }

        return $records->values()->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function databaseRecords(): Collection
    {
        $employeeId = auth()->id();

        if (! $employeeId) {
            return collect();
        }

        try {
            if (! Schema::hasTable('reworking_cost_records')) {
                return collect();
            }

            return ReworkingCostRecord::query()
                ->where('employee_id', $employeeId)
                ->orderBy('record_date')
                ->orderBy('id')
                ->get()
                ->map(fn (ReworkingCostRecord $record): array => [
                    'id' => (int) $record->source_record_id,
                    'project_id' => $record->project_id,
                    'project' => $record->project_name,
                    'batch_number' => $record->batch_number,
                    'date' => $record->record_date->format('Y-m-d'),
                    'description' => $record->description,
                    'total_reworking_cost' => (string) $record->total_reworking_cost,
                    'input_vat' => (string) $record->input_vat,
                    'attachment' => $record->attachment,
                    'updated_at' => $record->updated_at?->toDateTimeString(),
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return collect();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sessionRecords(): array
    {
        $records = session()->get($this->sessionKey(), []);

        return is_array($records) ? $records : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function saveRecords(array $records): void
    {
        session()->put($this->sessionKey(), array_values($records));
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function nextId(array $records): int
    {
        return (int) collect($records)->max('id') + 1;
    }

    private function projectsExist(): bool
    {
        return DemolitionProject::query()->exists();
    }

    private function attachmentUrl(?array $attachment): ?string
    {
        if (! $attachment || empty($attachment['filename'])) {
            return null;
        }

        try {
            $file = TemporaryUploadedFile::createFromLivewire($attachment['filename']);

            return $file->exists() ? $file->temporaryUrl() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function sessionKey(): string
    {
        return 'demo.reworking-cost-records.employee.'.(auth()->id() ?? 'guest');
    }

    /**
     * Mirror a saved session record to the source-keyed table used by the VAT
     * compliance module. updateOrCreate keeps edits duplicate-free.
     *
     * @param  array<string, mixed>  $record
     */
    private function persistRecord(array $record): void
    {
        $employeeId = auth()->id();

        if (! $employeeId) {
            return;
        }

        try {
            ReworkingCostRecord::query()->updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'source_record_id' => $record['id'],
                ],
                [
                    'project_id' => $record['project_id'] ?? null,
                    'project_name' => $record['project'],
                    'batch_number' => $record['batch_number'],
                    'record_date' => $record['date'],
                    'description' => $record['description'],
                    'total_reworking_cost' => $record['total_reworking_cost'],
                    'input_vat' => $record['input_vat'],
                    'attachment' => $record['attachment'] ?? null,
                ],
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function removePersistedRecord(int $id): void
    {
        $employeeId = auth()->id();

        if (! $employeeId) {
            return;
        }

        try {
            ReworkingCostRecord::query()
                ->where('employee_id', $employeeId)
                ->where('source_record_id', $id)
                ->delete();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
