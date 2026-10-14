<?php

namespace App\Services\Inventory;

use App\Models\InventoryReceiptAttachment;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a receipt attachment row and its stored file together.
 */
class DeleteInventoryReceiptAttachment
{
    public function __construct(private readonly ReceiptFileStorage $storage) {}

    public function handle(InventoryReceiptAttachment $attachment): void
    {
        DB::transaction(function () use ($attachment): void {
            $this->storage->delete($attachment->path, $attachment->disk);
            $attachment->delete();
        });
    }
}
