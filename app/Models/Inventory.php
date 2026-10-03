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
        'reorder_level',
        'image',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'reorder_level' => 'decimal:2',
    ];
}