<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DemolitionProject extends Model
{
    protected $fillable = [
        'name',
        'location',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function recoveredMaterials(): HasMany
    {
        return $this->hasMany(RecoveredMaterial::class)->orderByDesc('id');
    }

    protected static function booted(): void
    {
        static::creating(function (DemolitionProject $project): void {
            $project->code ??= 'TMP-'.Str::uuid();
        });

        static::created(function (DemolitionProject $project): void {
            $project->forceFill([
                'code' => 'DEM-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT),
            ])->saveQuietly();
        });
    }
}
