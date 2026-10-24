<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPostingMapping extends Model
{
    public const REQUIRED_CLASSIFICATIONS = [
        'cash' => ['cash'],
        'bank' => ['bank'],
        'card_clearing' => ['card_clearing', 'bank'],
        'sales' => ['sales'],
        'output_vat' => ['output_vat'],
        'cogs' => ['cost_of_goods_sold'],
        'inventory' => ['inventory'],
        'accounts_payable' => ['accounts_payable'],
    ];

    protected static function booted(): void
    {
        static::saving(function (AccountingPostingMapping $mapping): void {
            if ($mapping->exists && $mapping->isDirty(['source', 'accounting_account_id'])) {
                $mapping->approved_at = null;
                $mapping->approved_by = null;
            }
        });
    }

    protected $fillable = ['source', 'accounting_account_id', 'approved_at', 'approved_by'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function isApprovedForPosting(): bool
    {
        $account = $this->account;

        return $this->approved_at !== null
            && $account?->isApprovedForPosting() === true
            && self::acceptsClassification($this->source, $account->classification);
    }

    public static function acceptsClassification(string $source, string $classification): bool
    {
        $expected = self::REQUIRED_CLASSIFICATIONS[$source] ?? null;

        return $expected === null || in_array($classification, (array) $expected, true);
    }
}
