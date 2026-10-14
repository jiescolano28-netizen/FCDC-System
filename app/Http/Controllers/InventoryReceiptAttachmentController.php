<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryReceiptAttachmentsRequest;
use App\Models\Inventory;
use App\Models\InventoryReceiptAttachment;
use App\Services\Inventory\DeleteInventoryReceiptAttachment;
use App\Services\Inventory\ListInventoryReceiptAttachments;
use App\Services\Inventory\ReceiptFileStorage;
use App\Services\Inventory\StoreInventoryReceiptAttachments;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * JSON/API surface for inventory receipt attachments.
 *
 * Thin by design: it validates through the Form Request, delegates all business
 * logic to the App\Services\Inventory services, and shapes the HTTP response.
 */
class InventoryReceiptAttachmentController extends Controller
{
    /**
     * GET /inventory/{inventory}/receipts
     */
    public function index(Inventory $inventory, ListInventoryReceiptAttachments $list): JsonResponse
    {
        $attachments = $list->handle($inventory)->map(fn (InventoryReceiptAttachment $attachment) => [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'uploaded_by' => $attachment->uploader?->username,
            'created_at' => $attachment->created_at?->toIso8601String(),
            'download_url' => route('inventory.receipts.download', $attachment),
        ]);

        return response()->json($attachments);
    }

    /**
     * POST /inventory/{inventory}/receipts
     */
    public function store(
        StoreInventoryReceiptAttachmentsRequest $request,
        Inventory $inventory,
        StoreInventoryReceiptAttachments $store,
    ): JsonResponse {
        $attachments = $store->handle(
            $inventory,
            $request->file('receiptFiles'),
            $request->user()?->id,
        );

        return response()->json([
            'message' => 'Receipt attachments uploaded successfully.',
            'attachments' => $attachments,
        ], 201);
    }

    /**
     * GET /inventory/receipts/{attachment}/download
     */
    public function download(InventoryReceiptAttachment $attachment, ReceiptFileStorage $storage): StreamedResponse
    {
        abort_unless($storage->exists($attachment->path, $attachment->disk), 404);

        return Storage::disk($attachment->disk)
            ->download($attachment->path, $attachment->original_name);
    }

    /**
     * GET /inventory/receipts/{attachment}/preview
     */
    public function preview(InventoryReceiptAttachment $attachment, ReceiptFileStorage $storage): StreamedResponse
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
     * DELETE /inventory/receipts/{attachment}
     */
    public function destroy(InventoryReceiptAttachment $attachment, DeleteInventoryReceiptAttachment $delete): JsonResponse
    {
        $delete->handle($attachment);

        return response()->json(['message' => 'Receipt attachment deleted.']);
    }
}
