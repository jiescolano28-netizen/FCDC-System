<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReworkingCostRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'source_record_id',
        'project_id',
        'project_name',
        'batch_number',
        'record_date',
        'description',
        'total_reworking_cost',
        'input_vat',
        'attachment',
    ];

    protected $casts = [
        'record_date' => 'date:Y-m-d',
        'total_reworking_cost' => 'decimal:2',
        'input_vat' => 'decimal:2',
        'attachment' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DemolitionProject::class, 'project_id');
    }
}
