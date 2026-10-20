<?php

namespace App\Services\Inventory;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Low-level file storage for inventory batch category attachments.
 *
 * Keeps filesystem concerns out of the domain services so persistence logic
 * and storage logic stay separate and independently testable.
 */
class BatchCategoryAttachmentFileStorage
{
    public const DISK = 'local';

    public const DIRECTORY = 'inventory/batch-categories';

    /**
     * Persist an uploaded attachment file and return its metadata.
     *
     * @return array{path: string, original_name: string, mime_type: ?string, size_bytes: int}
     */
    public function store(UploadedFile $file): array
    {
        return [
            'path' => $file->store(self::DIRECTORY, self::DISK),
            'original_name' => $file->getClientOriginalName(),
            // Detect from file contents. Livewire's TemporaryUploadedFile does not
            // carry a client mime type (getClientMimeType() returns
            // application/octet-stream), so getMimeType() is the reliable source.
            'mime_type' => $this->resolveMimeType($file),
            'size_bytes' => (int) $file->getSize(),
        ];
    }

    private function resolveMimeType(UploadedFile $file): string
    {
        $detected = $file->getMimeType();

        if ($detected && $detected !== 'application/octet-stream') {
            return $detected;
        }

        $client = $file->getClientMimeType();

        return $client && $client !== 'application/octet-stream' ? $client : 'application/octet-stream';
    }

    public function delete(?string $path, string $disk = self::DISK): void
    {
        if ($this->exists($path, $disk)) {
            Storage::disk($disk)->delete($path);
        }
    }

    public function exists(?string $path, string $disk = self::DISK): bool
    {
        return $path !== null && Storage::disk($disk)->exists($path);
    }
}
