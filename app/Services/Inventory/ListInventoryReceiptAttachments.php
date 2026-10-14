<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\InventoryReceiptAttachment;
use Illuminate\Support\Collection;

/**
 * Lists the receipt attachments for an inventory item.
 */
class ListInventoryReceiptAttachments
{
    /**
     * @return Collection<int, InventoryReceiptAttachment>
     */
    public function handle(Inventory $inventory): Collection
    {
        return $inventory->receiptAttachments()->with('uploader')->get();
    }
}
