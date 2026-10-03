<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
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
