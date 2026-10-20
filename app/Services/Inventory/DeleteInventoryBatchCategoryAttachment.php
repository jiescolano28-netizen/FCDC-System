<?php

namespace App\Services\Inventory;

use App\Models\InventoryBatchCategoryAttachment;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a batch category attachment row and its stored file together.
 */
class DeleteInventoryBatchCategoryAttachment
{
    public function __construct(private readonly BatchCategoryAttachmentFileStorage $storage) {}

    public function handle(InventoryBatchCategoryAttachment $attachment): void
    {
        DB::transaction(function () use ($attachment): void {
            $this->storage->delete($attachment->path, $attachment->disk);
            $attachment->delete();
        });
    }
}
