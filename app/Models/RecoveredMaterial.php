<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RecoveredMaterial extends Model
{
    protected $fillable = [
        'demolition_project_id',
        'recorded_by',
        'material',
        'quantity',
        'unit',
        'condition',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function (RecoveredMaterial $recovery): void {
            if ($recovery->assessments()->exists()) {
                throw new \LogicException('Assessed recovered materials are immutable; post a linked correction and reassessment.');
            }
        });

        static::deleting(fn () => throw new \LogicException('Recovered-material records are retained for stock-history traceability.'));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DemolitionProject::class, 'demolition_project_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(RecoveredMaterialAssessment::class)->orderByDesc('id');
    }

    public function latestAssessment(): HasOne
    {
        return $this->hasOne(RecoveredMaterialAssessment::class)->latestOfMany();
    }
}
