<?php

namespace App\Livewire\Inventory;

use App\Http\Requests\StoreInventoryBatchCategoryAttachmentsRequest;
use App\Models\InventoryBatchCategory;
use App\Models\InventoryBatchCategoryAttachment;
use App\Services\Inventory\DeleteInventoryBatchCategoryAttachment;
use App\Services\Inventory\ListInventoryBatchCategoryAttachments;
use App\Services\Inventory\StoreInventoryBatchCategoryAttachments;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class BatchCategoryManagement extends Component
{
    use WithFileUploads;

    public string $search = '';

    public bool $showDeleted = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    /**
     * Batch category whose attachments are being managed, if any.
     */
    #[Locked]
    public ?int $attachmentsCategoryId = null;

    /**
     * Files staged for upload; no count limit, per-file size/type enforced by
     * StoreInventoryBatchCategoryAttachmentsRequest.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $attachmentFiles = [];

    /**
     * Attachment currently shown in the preview modal, if any.
     */
    public ?int $previewAttachmentId = null;

    public function create(): void
    {
        $this->authorizePermission('inventory.create');
        $this->resetForm();
        $this->dispatch('batch-category-modal-open');
    }

    public function edit(int $id): void
    {
        $this->authorizePermission('inventory.update');

        $category = InventoryBatchCategory::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->description = $category->description ?? '';
        $this->resetValidation();
        $this->dispatch('batch-category-modal-open');
    }

    public function save(): void
    {
        $this->authorizePermission($this->editingId ? 'inventory.update' : 'inventory.create');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $category = $this->editingId
            ? InventoryBatchCategory::findOrFail($this->editingId)
            : new InventoryBatchCategory;
        $category->name = $validated['name'];
        $category->description = $validated['description'] ?? null;
        $category->updated_by = auth()->id();

        if (! $category->exists) {
            $category->created_by = auth()->id();
        }

        $category->save();
        $this->resetForm();
        $this->dispatch('batch-category-saved');
    }

    public function delete(int $id): void
    {
        $this->authorizePermission('inventory.delete');

        InventoryBatchCategory::findOrFail($id)->delete();

        if ($this->attachmentsCategoryId === $id) {
            $this->closeAttachments();
            $this->dispatch('batch-category-attachments-modal-close');
        }

        $this->resetForm();
    }

    public function restore(int $id): void
    {
        $this->authorizePermission('inventory.delete');

        InventoryBatchCategory::onlyTrashed()->findOrFail($id)->restore();
    }

    public function manageAttachments(int $id): void
    {
        $this->authorizePermission('inventory.view');

        $category = InventoryBatchCategory::findOrFail($id);
        $this->attachmentsCategoryId = $category->id;
        $this->reset(['attachmentFiles', 'previewAttachmentId']);
        $this->resetValidation();
        $this->dispatch('batch-category-attachments-modal-open');
    }

    public function closeAttachments(): void
    {
        $this->reset(['attachmentsCategoryId', 'attachmentFiles', 'previewAttachmentId']);
        $this->resetValidation();
    }

    public function uploadAttachments(): void
    {
        $this->authorizePermission('inventory.update');

        $validated = $this->validate(StoreInventoryBatchCategoryAttachmentsRequest::uploadRules());

        app(StoreInventoryBatchCategoryAttachments::class)->handle(
            InventoryBatchCategory::findOrFail($this->attachmentsCategoryId),
            $validated['attachmentFiles'],
            auth()->id(),
        );

        $this->reset('attachmentFiles');
        $this->dispatch('batch-category-attachments-uploaded');
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $this->authorizePermission('inventory.update');

        $attachment = InventoryBatchCategoryAttachment::findOrFail($attachmentId);
        abort_unless($attachment->inventory_batch_category_id === $this->attachmentsCategoryId, 404);

        app(DeleteInventoryBatchCategoryAttachment::class)->handle($attachment);
    }

    public function previewAttachment(int $attachmentId): void
    {
        $attachment = InventoryBatchCategoryAttachment::findOrFail($attachmentId);
        abort_unless($attachment->inventory_batch_category_id === $this->attachmentsCategoryId, 404);

        $this->previewAttachmentId = $attachmentId;
    }

    public function closeAttachmentPreview(): void
    {
        $this->previewAttachmentId = null;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description']);
        $this->resetValidation();
    }

    public function render()
    {
        $categories = ($this->showDeleted && auth()->user()->can('inventory.delete')
            ? InventoryBatchCategory::onlyTrashed()
            : InventoryBatchCategory::query())
            ->when(trim($this->search) !== '', function ($query): void {
                $search = trim($this->search);
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->get();

        $attachmentsCategory = $this->attachmentsCategoryId
            ? InventoryBatchCategory::find($this->attachmentsCategoryId)
            : null;

        $attachments = $attachmentsCategory
            ? app(ListInventoryBatchCategoryAttachments::class)->handle($attachmentsCategory)
            : collect();

        $previewAttachment = $this->previewAttachmentId
            ? $attachments->firstWhere('id', $this->previewAttachmentId)
            : null;

        return view('livewire.inventory.batch-category-management', [
            'categories' => $categories,
            'attachmentsCategory' => $attachmentsCategory,
            'attachments' => $attachments,
            'previewAttachment' => $previewAttachment,
        ])->layout('layouts.app', ['title' => 'Inventory batch categories']);
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
