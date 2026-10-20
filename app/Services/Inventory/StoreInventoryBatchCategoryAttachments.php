<?php

namespace App\Services\Inventory;

use App\Models\InventoryBatchCategory;
use App\Models\InventoryBatchCategoryAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Stores one or more uploaded files against a batch category.
 *
 * Any number of files may be uploaded in a single call; only the per-file size
 * policy limits the upload. If persistence fails partway through, every file
 * already written to disk is removed.
 */
class StoreInventoryBatchCategoryAttachments
{
    public function __construct(private readonly BatchCategoryAttachmentFileStorage $storage) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, InventoryBatchCategoryAttachment>
     */
    public function handle(InventoryBatchCategory $category, array $files, ?int $employeeId): Collection
    {
        /** @var array<int, string> $storedPaths */
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($category, $files, $employeeId, &$storedPaths) {
                $attachments = collect();

                foreach ($files as $file) {
                    $meta = $this->storage->store($file);
                    $storedPaths[] = $meta['path'];

                    $attachments->push($category->attachments()->create([
                        'uploaded_by' => $employeeId,
                        'original_name' => $meta['original_name'],
                        'path' => $meta['path'],
                        'disk' => BatchCategoryAttachmentFileStorage::DISK,
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
