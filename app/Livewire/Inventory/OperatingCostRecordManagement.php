<?php

namespace App\Livewire\Inventory;

use App\Models\DemolitionProject;
use App\Models\OperatingCostRecord;
use App\Models\ReworkingCostRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Operating Cost Record module.
 *
 * The module keeps the original batch reworking-cost register, including the
 * input VAT that the VAT compliance screens read, and adds a register of
 * day-to-day operating expenses. Batch records continue to be mirrored to the
 * source-keyed reworking table so the saved input VAT remains available to
 * VAT Records, VAT Summary, VAT Report, and VAT Return Preparation. These
 * registers do not post journals themselves; approved source journals are
 * generated from Journal Entry.
 */
class OperatingCostRecordManagement extends Component
{
    use WithFileUploads;

    /**
     * Fixed expense categories available in the category dropdown.
     *
     * @var list<string>
     */
    public const EXPENSE_CATEGORIES = [
        'Rent Expense',
        'Utilities Expense',
        'Repairs and Maintenance Expense',
        'Office Supplies Expense',
        'Transportation and Delivery Expense',
        'Cleaning Supplies Expense',
        'Other Operating Expenses',
    ];

    /**
     * @var list<string>
     */
    public const TABS = ['batch', 'expenses'];

    public const ATTACHMENT_DISK = 'local';

    public const ATTACHMENT_DIRECTORY = 'inventory/operating-cost-records';

    /**
     * Session key used by the batch register. The VAT compliance module also
     * reads this key, so it must stay aligned with RecordedInputVat.
     */
    public const SESSION_KEY_PREFIX = 'demo.reworking-cost-records.employee.';

    public string $activeTab = 'batch';

    public string $batchSearch = '';

    #[Locked]
    public ?int $batchEditingId = null;

    public ?int $projectId = null;

    public string $projectName = '';

    public string $batchNumber = '';

    public string $batchDate = '';

    public string $batchDescription = '';

    public string $totalReworkingCost = '';

    public string $inputVat = '0.00';

    public string $batchPaymentMethod = '';

    /**
     * New supporting document staged for the batch record being created or
     * edited.
     */
    public $batchAttachmentFile = null;

    /**
     * Supporting document already stored on the batch record being edited.
     *
     * @var array{name: string, size: int, filename: string}|null
     */
    public ?array $existingBatchAttachment = null;

    public bool $removeExistingBatchAttachment = false;

    public string $search = '';

    public string $categoryFilter = '';

    #[Locked]
    public ?int $editingId = null;

    public string $expenseDate = '';

    public string $referenceNumber = '';

    public string $expenseCategory = '';

    public string $expenseDescription = '';

    public string $amount = '';

    public string $paymentMethod = '';

    /**
     * New receipt or supporting document staged for the expense record being
     * created or edited.
     */
    public $attachmentFile = null;

    /**
     * Attachment already stored on the expense record being edited, if any.
     *
     * @var array{name: string, size: int}|null
     */
    public ?array $existingAttachment = null;

    public bool $removeExistingAttachment = false;

    public string $statusMessage = '';

    public function mount(): void
    {
        $this->batchDate = now('Asia/Manila')->toDateString();
        $this->expenseDate = now('Asia/Manila')->toDateString();
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, self::TABS, true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->statusMessage = '';
        $this->resetValidation();
    }

    public function createBatch(): void
    {
        $this->authorizePermission('inventory.create');
        $this->resetBatchForm();
        $this->statusMessage = '';
        $this->dispatch('operating-cost-batch-form-open');
    }

    public function editBatch(int $id): void
    {
        $this->authorizePermission('inventory.update');

        $record = collect($this->batchRecords())->firstWhere('id', $id);

        if (! $record) {
            return;
        }

        $this->batchEditingId = $record['id'];
        $this->projectId = $record['project_id'] ?? null;
        $this->projectName = $record['project_id'] ? '' : $record['project'];
        $this->batchNumber = $record['batch_number'];
        $this->batchDate = $record['date'];
        $this->batchDescription = $record['description'];
        $this->totalReworkingCost = $record['total_reworking_cost'];
        $this->inputVat = $record['input_vat'];
        $this->batchPaymentMethod = $record['payment_method'] ?? '';
        $this->existingBatchAttachment = $record['attachment'] ?? null;
        $this->removeExistingBatchAttachment = false;
        $this->batchAttachmentFile = null;
        $this->statusMessage = '';
        $this->resetValidation();
        $this->dispatch('operating-cost-batch-form-open');
    }

    public function saveBatch(): void
    {
        $this->authorizePermission($this->batchEditingId ? 'inventory.update' : 'inventory.create');

        $validated = $this->validate($this->batchRules());

        $projectId = null;
        $projectLabel = trim($this->projectName);

        if ($this->projectsExist()) {
            $project = DemolitionProject::findOrFail($validated['projectId']);
            $projectId = $project->id;
            $projectLabel = $project->code.' · '.$project->name;
        }

        $attachment = $this->existingBatchAttachment;

        if ($this->removeExistingBatchAttachment) {
            $attachment = null;
        }

        if ($this->batchAttachmentFile) {
            $attachment = [
                'name' => $this->batchAttachmentFile->getClientOriginalName(),
                'size' => (int) $this->batchAttachmentFile->getSize(),
                'filename' => $this->batchAttachmentFile->getFilename(),
            ];
        }

        $records = $this->batchRecords();
        $wasEditing = $this->batchEditingId !== null;

        $record = [
            'id' => $this->batchEditingId ?? $this->nextBatchId($records),
            'project_id' => $projectId,
            'project' => $projectLabel,
            'batch_number' => trim($validated['batchNumber']),
            'date' => $validated['batchDate'],
            'description' => trim($validated['batchDescription']),
            'total_reworking_cost' => number_format((float) $validated['totalReworkingCost'], 2, '.', ''),
            'input_vat' => number_format((float) ($validated['inputVat'] ?? 0), 2, '.', ''),
            'payment_method' => trim((string) ($validated['batchPaymentMethod'] ?? '')) ?: null,
            'attachment' => $attachment,
            'updated_at' => now('UTC')->toDateTimeString(),
        ];

        if ($wasEditing) {
            $records = array_map(
                fn (array $existing): array => $existing['id'] === $record['id'] ? $record : $existing,
                $records,
            );
        } else {
            $records[] = $record;
        }

        $this->saveBatchRecords($records);
        $this->persistBatchRecord($record);
        $this->resetBatchForm();
        $this->statusMessage = $wasEditing
            ? 'Batch reworking cost record updated.'
            : 'Batch reworking cost record added.';
        $this->dispatch('operating-cost-batch-form-close');
    }

    public function deleteBatch(int $id): void
    {
        $this->authorizePermission('inventory.delete');

        $records = collect($this->batchRecords())->reject(fn (array $record): bool => $record['id'] === $id)->values()->all();
        $this->saveBatchRecords($records);
        $this->removePersistedBatchRecord($id);

        if ($this->batchEditingId === $id) {
            $this->resetBatchForm();
            $this->dispatch('operating-cost-batch-form-close');
        }

        $this->statusMessage = 'Batch reworking cost record deleted.';
    }

    public function updatedBatchAttachmentFile(): void
    {
        $this->validateOnly('batchAttachmentFile', [
            'batchAttachmentFile' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);
    }

    public function resetBatchForm(): void
    {
        $this->reset([
            'batchEditingId',
            'projectId',
            'projectName',
            'batchNumber',
            'batchDescription',
            'totalReworkingCost',
            'batchPaymentMethod',
            'batchAttachmentFile',
            'existingBatchAttachment',
            'removeExistingBatchAttachment',
        ]);

        $this->batchDate = now('Asia/Manila')->toDateString();
        $this->inputVat = '0.00';
        $this->resetValidation();
    }

    public function create(): void
    {
        $this->authorizePermission('inventory.create');
        $this->resetForm();
        $this->statusMessage = '';
        $this->dispatch('operating-cost-record-form-open');
    }

    public function edit(int $id): void
    {
        $this->authorizePermission('inventory.update');

        $record = OperatingCostRecord::findOrFail($id);

        $this->editingId = $record->id;
        $this->expenseDate = $record->expense_date?->format('Y-m-d') ?? '';
        $this->referenceNumber = $record->reference_number;
        $this->expenseCategory = $record->expense_category;
        $this->expenseDescription = $record->description;
        $this->amount = number_format((float) $record->amount, 2, '.', '');
        $this->paymentMethod = $record->payment_method;
        $this->existingAttachment = $record->attachment_path
            ? [
                'name' => $record->attachment_name ?: 'Supporting document',
                'size' => (int) ($record->attachment_size ?? 0),
            ]
            : null;
        $this->removeExistingAttachment = false;
        $this->attachmentFile = null;
        $this->statusMessage = '';
        $this->resetValidation();
        $this->dispatch('operating-cost-record-form-open');
    }

    public function save(): void
    {
        $this->authorizePermission($this->editingId ? 'inventory.update' : 'inventory.create');

        $validated = $this->validate($this->rules());

        $record = $this->editingId
            ? OperatingCostRecord::findOrFail($this->editingId)
            : new OperatingCostRecord;

        $wasEditing = $record->exists;
        $oldPath = $record->attachment_path;
        $oldDisk = $record->attachment_disk ?: self::ATTACHMENT_DISK;
        $storedFile = null;

        try {
            if ($this->attachmentFile) {
                $storedFile = [
                    'path' => $this->attachmentFile->store(self::ATTACHMENT_DIRECTORY, self::ATTACHMENT_DISK),
                    'name' => $this->attachmentFile->getClientOriginalName(),
                    'mime' => $this->attachmentFile->getMimeType() ?: $this->attachmentFile->getClientMimeType(),
                    'size' => (int) $this->attachmentFile->getSize(),
                    'disk' => self::ATTACHMENT_DISK,
                ];
            }

            $attributes = [
                'expense_date' => $validated['expenseDate'],
                'reference_number' => trim($validated['referenceNumber']),
                'expense_category' => $validated['expenseCategory'],
                'description' => trim($validated['expenseDescription']),
                'amount' => number_format((float) $validated['amount'], 2, '.', ''),
                'payment_method' => trim($validated['paymentMethod']),
            ];

            if (! $wasEditing) {
                $attributes['employee_id'] = auth()->id();
            }

            if ($storedFile) {
                $attributes['attachment_path'] = $storedFile['path'];
                $attributes['attachment_name'] = $storedFile['name'];
                $attributes['attachment_mime'] = $storedFile['mime'];
                $attributes['attachment_size'] = $storedFile['size'];
                $attributes['attachment_disk'] = $storedFile['disk'];
            } elseif ($this->removeExistingAttachment) {
                $attributes['attachment_path'] = null;
                $attributes['attachment_name'] = null;
                $attributes['attachment_mime'] = null;
                $attributes['attachment_size'] = null;
                $attributes['attachment_disk'] = null;
            }

            $record->fill($attributes);
            $record->save();

            if ($oldPath && ($storedFile || $this->removeExistingAttachment)) {
                Storage::disk($oldDisk)->delete($oldPath);
            }
        } catch (Throwable $exception) {
            if ($storedFile) {
                Storage::disk(self::ATTACHMENT_DISK)->delete($storedFile['path']);
            }

            throw $exception;
        }

        $this->resetForm();
        $this->statusMessage = $wasEditing
            ? 'Operating cost record updated.'
            : 'Operating cost record added.';
        $this->dispatch('operating-cost-record-form-close');
    }

    public function delete(int $id): void
    {
        $this->authorizePermission('inventory.delete');

        $record = OperatingCostRecord::findOrFail($id);
        $disk = $record->attachment_disk ?: self::ATTACHMENT_DISK;

        if ($record->attachment_path) {
            Storage::disk($disk)->delete($record->attachment_path);
        }

        $record->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
            $this->dispatch('operating-cost-record-form-close');
        }

        $this->statusMessage = 'Operating cost record deleted.';
    }

    public function updatedAttachmentFile(): void
    {
        $this->validateOnly('attachmentFile', [
            'attachmentFile' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
    }

    public function downloadAttachment(int $id)
    {
        $this->authorizePermission('inventory.view');

        $record = OperatingCostRecord::findOrFail($id);
        $disk = $record->attachment_disk ?: self::ATTACHMENT_DISK;

        abort_unless(
            $record->attachment_path && Storage::disk($disk)->exists($record->attachment_path),
            404,
        );

        return Storage::disk($disk)->download(
            $record->attachment_path,
            $record->attachment_name ?: 'operating-cost-document',
        );
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId',
            'referenceNumber',
            'expenseCategory',
            'expenseDescription',
            'amount',
            'paymentMethod',
            'attachmentFile',
            'existingAttachment',
            'removeExistingAttachment',
        ]);

        $this->expenseDate = now('Asia/Manila')->toDateString();
        $this->resetValidation();
    }

    public function render()
    {
        $batchRecords = collect($this->batchRecords())
            ->when(trim($this->batchSearch) !== '', function ($records) {
                $search = Str::lower(trim($this->batchSearch));

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

        $batchAttachmentUrls = $batchRecords->mapWithKeys(fn (array $record): array => [
            $record['id'] => $this->batchAttachmentUrl($record['attachment'] ?? null),
        ]);

        $batchSummary = [
            'count' => $batchRecords->count(),
            'total' => $batchRecords->sum(fn (array $record): float => (float) $record['total_reworking_cost']),
            'vat' => $batchRecords->sum(fn (array $record): float => (float) $record['input_vat']),
        ];
        $batchSummary['total_with_vat'] = $batchSummary['total'] + $batchSummary['vat'];

        $records = OperatingCostRecord::query()
            ->when(trim($this->search) !== '', function ($query) {
                $search = '%'.trim($this->search).'%';

                $query->where(function ($query) use ($search) {
                    $query->where('reference_number', 'like', $search)
                        ->orWhere('expense_category', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhere('payment_method', 'like', $search);
                });
            })
            ->when($this->categoryFilter !== '', fn ($query) => $query->where('expense_category', $this->categoryFilter))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $allRecords = OperatingCostRecord::query()->get(['amount', 'attachment_path', 'expense_date']);

        $summary = [
            'count' => $allRecords->count(),
            'total' => (float) $allRecords->sum('amount'),
            'month' => (float) $allRecords
                ->filter(fn (OperatingCostRecord $record): bool => $record->expense_date?->isSameMonth(now('Asia/Manila')))
                ->sum('amount'),
            'with_documents' => $allRecords->whereNotNull('attachment_path')->count(),
        ];

        return view('livewire.inventory.operating-cost-record-management', [
            'batchRecords' => $batchRecords,
            'batchAttachmentUrls' => $batchAttachmentUrls,
            'batchSummary' => $batchSummary,
            'records' => $records,
            'summary' => $summary,
            'expenseCategories' => self::EXPENSE_CATEGORIES,
            'projects' => DemolitionProject::query()->orderBy('name')->get(['id', 'code', 'name']),
        ])->layout('layouts.app', ['title' => 'Operating cost record']);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function batchRules(): array
    {
        $rules = [
            'batchNumber' => ['required', 'string', 'max:100'],
            'batchDate' => ['required', 'date'],
            'batchDescription' => ['required', 'string', 'max:2000'],
            'totalReworkingCost' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'inputVat' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'batchPaymentMethod' => ['nullable', 'string', 'max:100'],
            'batchAttachmentFile' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];

        if ($this->projectsExist()) {
            $rules['projectId'] = ['required', 'integer', 'exists:demolition_projects,id'];
        } else {
            $rules['projectName'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'expenseDate' => ['required', 'date'],
            'referenceNumber' => ['required', 'string', 'max:100'],
            'expenseCategory' => ['required', 'string', Rule::in(self::EXPENSE_CATEGORIES)],
            'expenseDescription' => ['required', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'paymentMethod' => ['required', 'string', 'max:100'],
            'attachmentFile' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * Saved batch records for the signed-in employee.
     *
     * The source-keyed table is the durable copy used by VAT compliance. The
     * session copy is retained for records created before the table existed
     * and for the same-request interface state. Merging by source record id
     * keeps both copies from being shown twice.
     *
     * @return array<int, array<string, mixed>>
     */
    private function batchRecords(): array
    {
        $records = $this->databaseBatchRecords()->keyBy('id');

        foreach ($this->sessionBatchRecords() as $record) {
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
    private function databaseBatchRecords(): Collection
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
                    'payment_method' => $record->payment_method,
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
    private function sessionBatchRecords(): array
    {
        $records = session()->get($this->batchSessionKey(), []);

        return is_array($records) ? $records : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function saveBatchRecords(array $records): void
    {
        session()->put($this->batchSessionKey(), array_values($records));
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function nextBatchId(array $records): int
    {
        return (int) collect($records)->max('id') + 1;
    }

    private function projectsExist(): bool
    {
        return DemolitionProject::query()->exists();
    }

    private function batchAttachmentUrl(?array $attachment): ?string
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

    private function batchSessionKey(): string
    {
        return self::SESSION_KEY_PREFIX.(auth()->id() ?? 'guest');
    }

    /**
     * Mirror a saved batch record to the source-keyed table used by the VAT
     * compliance module. updateOrCreate keeps edits duplicate-free.
     *
     * @param  array<string, mixed>  $record
     */
    private function persistBatchRecord(array $record): void
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
                    'payment_method' => $record['payment_method'] ?? null,
                    'attachment' => $record['attachment'] ?? null,
                ],
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function removePersistedBatchRecord(int $id): void
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
