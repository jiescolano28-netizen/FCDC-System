<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatingCostRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'expense_date',
        'reference_number',
        'expense_category',
        'description',
        'amount',
        'payment_method',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'attachment_disk',
    ];

    protected $casts = [
        'expense_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'attachment_size' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Human-readable size of the uploaded receipt or supporting document.
     */
    public function getAttachmentHumanSizeAttribute(): ?string
    {
        $bytes = $this->attachment_size;

        if ($bytes === null) {
            return null;
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0).' KB';
        }

        return $bytes.' B';
    }
}
