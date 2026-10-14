<?php

namespace App\Livewire\Inventory;

use App\Http\Requests\StoreInventoryReceiptAttachmentsRequest;
use App\Models\Inventory;
use App\Models\InventoryReceiptAttachment;
use App\Services\Inventory\DeleteInventoryReceiptAttachment;
use App\Services\Inventory\ListInventoryReceiptAttachments;
use App\Services\Inventory\StoreInventoryReceiptAttachments;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class InventoryProfile extends Component
{
    use WithFileUploads;

    public int $inventoryId;

    /**
     * Files staged for upload; no count limit, per-file size/type enforced by
     * StoreInventoryReceiptAttachmentsRequest.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $receiptFiles = [];

    /**
     * Attachment currently shown in the preview modal, if any.
     */
    public ?int $previewAttachmentId = null;

    public function mount(int $id): void
    {
        $this->inventoryId = $id;
    }

    public function uploadReceipts(): void
    {
        abort_unless(auth()->user()?->can('inventory.update'), 403);

        $validated = $this->validate(StoreInventoryReceiptAttachmentsRequest::uploadRules());

        app(StoreInventoryReceiptAttachments::class)->handle(
            Inventory::findOrFail($this->inventoryId),
            $validated['receiptFiles'],
            auth()->id(),
        );

        $this->reset('receiptFiles');
        $this->dispatch('receipt-attachments-uploaded');
    }

    public function deleteReceipt(int $attachmentId): void
    {
        abort_unless(auth()->user()?->can('inventory.update'), 403);

        $attachment = InventoryReceiptAttachment::findOrFail($attachmentId);
        abort_unless($attachment->inventory_id === $this->inventoryId, 404);

        app(DeleteInventoryReceiptAttachment::class)->handle($attachment);
    }

    public function preview(int $attachmentId): void
    {
        $attachment = InventoryReceiptAttachment::findOrFail($attachmentId);
        abort_unless($attachment->inventory_id === $this->inventoryId, 404);

        $this->previewAttachmentId = $attachmentId;
    }

    public function closePreview(): void
    {
        $this->previewAttachmentId = null;
    }

    public function render()
    {
        $item = Inventory::query()
            ->with(['batchCategory'])
            ->with(['stockMovements' => fn (HasMany $query) => $query
                ->whereIn('type', ['purchase_receipt', 'stock_in'])
                ->with('poster')
                ->orderByDesc('effective_date')
                ->orderByDesc('posted_at')])
            ->findOrFail($this->inventoryId);

        $receiptAttachments = app(ListInventoryReceiptAttachments::class)->handle($item);

        $previewAttachment = $this->previewAttachmentId
            ? $receiptAttachments->firstWhere('id', $this->previewAttachmentId)
            : null;

        return view('livewire.inventory.inventory-profile', [
            'item' => $item,
            'receipts' => $item->stockMovements,
            'receiptAttachments' => $receiptAttachments,
            'previewAttachment' => $previewAttachment,
        ])->layout('layouts.app', ['title' => 'Inventory profile']);
    }
}
