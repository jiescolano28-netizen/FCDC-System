<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryReceiptAttachment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'inventory_id',
        'uploaded_by',
        'original_name',
        'path',
        'disk',
        'mime_type',
        'size_bytes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class)->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['inventory_id', 'original_name', 'mime_type', 'size_bytes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Image file extensions treated as previewable images.
     *
     * @var list<string>
     */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];

    /**
     * Whether the attachment can be rendered inline as an image preview.
     *
     * Falls back to the filename extension so rows stored before mime
     * detection was fixed (recorded as application/octet-stream) still preview.
     */
    public function isImage(): bool
    {
        if (str_starts_with((string) $this->mime_type, 'image/')) {
            return true;
        }

        $extension = strtolower(pathinfo((string) $this->original_name, PATHINFO_EXTENSION));

        return in_array($extension, self::IMAGE_EXTENSIONS, true);
    }

    /**
     * Human-readable file size (e.g. "1.4 MB").
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0).' KB';
        }

        return $bytes.' B';
    }
}
