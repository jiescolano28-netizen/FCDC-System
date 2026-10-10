<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccount extends Model
{
    protected static function booted(): void
    {
        static::saving(function (AccountingAccount $account): void {
            if ($account->exists && $account->getOriginal('used_at') !== null
                && $account->isDirty(['code', 'type', 'classification'])) {
                throw new DomainException('Used account identity is historically immutable.');
            }

            if ($account->exists && (
                $account->isDirty(['code', 'name', 'description', 'type', 'classification', 'normal_balance'])
                || ($account->isDirty('is_active') && $account->is_active)
            )) {
                $account->approved_at = null;
                $account->approved_by = null;
            }
        });

        static::deleting(function (AccountingAccount $account): void {
            if ($account->used_at !== null) {
                throw new DomainException('Used accounts cannot be deleted.');
            }
        });
    }

    protected $fillable = [
        'code', 'name', 'description', 'type', 'classification', 'normal_balance', 'is_active',
        'approved_at', 'approved_by', 'used_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'approved_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(AccountingPostingMapping::class);
    }

    public function markUsed(): void
    {
        $this->forceFill(['used_at' => $this->used_at ?? now()])->save();
    }

    public function isApprovedForPosting(): bool
    {
        return $this->is_active && $this->approved_at !== null;
    }
}
