<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OpeningInventoryValuationLine extends Model
{
    protected $fillable = ['inventory_id', 'quantity', 'carrying_value_cents'];

    protected $casts = ['quantity' => 'decimal:2'];

    protected static function booted(): void
    {
        static::creating(function (OpeningInventoryValuationLine $line): void {
            $line->assertEditableSchedule();
        });
        static::updating(function (OpeningInventoryValuationLine $line): void {
            $line->assertEditableSchedule();
        });
        static::deleting(function (OpeningInventoryValuationLine $line): void {
            $line->assertEditableSchedule();
        });
    }

    private function assertEditableSchedule(): void
    {
        $schedule = OpeningInventoryValuation::find($this->opening_inventory_valuation_id);
        $postedOpening = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->where('status', 'posted')->exists();
        if ($postedOpening || $schedule?->status === 'approved') {
            throw new LogicException('Approved opening inventory valuation lines are immutable.');
        }
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function valuation(): BelongsTo
    {
        return $this->belongsTo(OpeningInventoryValuation::class, 'opening_inventory_valuation_id');
    }
}
