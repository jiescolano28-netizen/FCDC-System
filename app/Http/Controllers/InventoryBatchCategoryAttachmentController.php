<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryBatchCategoryAttachmentsRequest;
use App\Models\InventoryBatchCategory;
use App\Models\InventoryBatchCategoryAttachment;
use App\Services\Inventory\BatchCategoryAttachmentFileStorage;
use App\Services\Inventory\DeleteInventoryBatchCategoryAttachment;
use App\Services\Inventory\ListInventoryBatchCategoryAttachments;
use App\Services\Inventory\StoreInventoryBatchCategoryAttachments;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * JSON/API surface for inventory batch category attachments.
 *
 * Thin by design: it validates through the Form Request, delegates all business
 * logic to the App\Services\Inventory services, and shapes the HTTP response.
 */
class InventoryBatchCategoryAttachmentController extends Controller
{
    /**
     * GET /inventory/batch-categories/{batchCategory}/attachments
     */
    public function index(InventoryBatchCategory $batchCategory, ListInventoryBatchCategoryAttachments $list): JsonResponse
    {
        $attachments = $list->handle($batchCategory)->map(fn (InventoryBatchCategoryAttachment $attachment) => [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'uploaded_by' => $attachment->uploader?->username,
            'created_at' => $attachment->created_at?->toIso8601String(),
            'download_url' => route('inventory.batch-categories.attachments.download', $attachment),
        ]);

        return response()->json($attachments);
    }

    /**
     * POST /inventory/batch-categories/{batchCategory}/attachments
     */
    public function store(
        StoreInventoryBatchCategoryAttachmentsRequest $request,
        InventoryBatchCategory $batchCategory,
        StoreInventoryBatchCategoryAttachments $store,
    ): JsonResponse {
        $attachments = $store->handle(
            $batchCategory,
            $request->file('attachmentFiles'),
            $request->user()?->id,
        );

        return response()->json([
            'message' => 'Batch category attachments uploaded successfully.',
            'attachments' => $attachments,
        ], 201);
    }

    /**
     * GET /inventory/batch-categories/attachments/{attachment}/download
     */
    public function download(InventoryBatchCategoryAttachment $attachment, BatchCategoryAttachmentFileStorage $storage): StreamedResponse
    {
        abort_unless($storage->exists($attachment->path, $attachment->disk), 404);

        return Storage::disk($attachment->disk)
            ->download($attachment->path, $attachment->original_name);
    }

    /**
     * GET /inventory/batch-categories/attachments/{attachment}/preview
     */
    public function preview(InventoryBatchCategoryAttachment $attachment, BatchCategoryAttachmentFileStorage $storage): StreamedResponse
    {
        abort_unless($storage->exists($attachment->path, $attachment->disk), 404);

        $disk = Storage::disk($attachment->disk);
        $mimeType = $attachment->mime_type;

        // Older rows may have recorded a generic octet-stream type; fall back to
        // content sniffing so images still render inline.
        if (! $mimeType || $mimeType === 'application/octet-stream') {
            $mimeType = $disk->mimeType($attachment->path) ?: 'application/octet-stream';
        }

        return $disk->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $mimeType],
        );
    }

    /**
     * DELETE /inventory/batch-categories/attachments/{attachment}
     */
    public function destroy(InventoryBatchCategoryAttachment $attachment, DeleteInventoryBatchCategoryAttachment $delete): JsonResponse
    {
        $delete->handle($attachment);

        return response()->json(['message' => 'Batch category attachment deleted.']);
    }
}
