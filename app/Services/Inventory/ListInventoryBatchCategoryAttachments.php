<?php

namespace App\Services\Inventory;

use App\Models\InventoryBatchCategory;
use App\Models\InventoryBatchCategoryAttachment;
use Illuminate\Support\Collection;

/**
 * Lists the attachment files for a batch category.
 */
class ListInventoryBatchCategoryAttachments
{
    /**
     * @return Collection<int, InventoryBatchCategoryAttachment>
     */
    public function handle(InventoryBatchCategory $category): Collection
    {
        return $category->attachments()->with('uploader')->get();
    }
}
