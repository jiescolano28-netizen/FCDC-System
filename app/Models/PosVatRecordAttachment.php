<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosVatRecordAttachment extends Model
{
    protected $fillable = [
        'pos_vat_record_id',
        'employee_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function vatRecord(): BelongsTo
    {
        return $this->belongsTo(PosVatRecord::class, 'pos_vat_record_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
