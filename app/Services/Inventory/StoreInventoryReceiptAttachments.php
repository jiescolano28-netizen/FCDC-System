<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\InventoryReceiptAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Stores one or more uploaded receipt files against an inventory item.
 *
 * Any number of files may be uploaded in a single call; only the per-file size
 * (and, later, type) policy limits the upload. If persistence fails partway
 * through, every file already written to disk is removed.
 */
class StoreInventoryReceiptAttachments
{
    public function __construct(private readonly ReceiptFileStorage $storage) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, InventoryReceiptAttachment>
     */
    public function handle(Inventory $inventory, array $files, ?int $employeeId): Collection
    {
        /** @var array<int, string> $storedPaths */
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($inventory, $files, $employeeId, &$storedPaths) {
                $attachments = collect();

                foreach ($files as $file) {
                    $meta = $this->storage->store($file);
                    $storedPaths[] = $meta['path'];

                    $attachments->push($inventory->receiptAttachments()->create([
                        'uploaded_by' => $employeeId,
                        'original_name' => $meta['original_name'],
                        'path' => $meta['path'],
                        'disk' => ReceiptFileStorage::DISK,
                        'mime_type' => $meta['mime_type'],
                        'size_bytes' => $meta['size_bytes'],
                    ]));
                }

                return $attachments;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                $this->storage->delete($path);
            }

            throw $exception;
        }
    }
}
