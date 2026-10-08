<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Inventory extends Model
{
    use LogsActivity;
    protected $fillable = [
        'name',
        'category',
        'qty',
        'unit',
        'unit_cost',
        'selling_price',
        'reorder_level',
        'image',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }


    protected $casts = [
        'qty' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'reorder_level' => 'decimal:2',
    ];

    public function scopeAvailableForSale($query)
    {
        return $query->whereNotNull('selling_price')->where('qty', '>', 0);
    }
}
