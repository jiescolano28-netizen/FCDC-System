<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccount extends Model
{
    private const POSTING_POLICY_HELD_CODES = ['1510', '2040', '3040', '3050', '5030'];

    private const CLASSIFICATIONS = [
        'cash' => ['Cash', 'Asset'],
        'bank' => ['Bank', 'Asset'],
        'accounts_receivable' => ['Accounts receivable', 'Asset'],
        'card_clearing' => ['Card settlement receivable / clearing', 'Asset'],
        'inventory' => ['Inventory', 'Asset'],
        'equipment' => ['Equipment', 'Asset'],
        'contra_asset' => ['Contra-asset', 'Asset'],
        'other_current_asset' => ['Other current asset', 'Asset'],
        'other_noncurrent_asset' => ['Other non-current asset', 'Asset'],
        'accounts_payable' => ['Accounts payable', 'Liability'],
        'output_vat' => ['Output VAT', 'Liability'],
        'input_vat' => ['Input VAT', 'Asset'],
        'other_current_liability' => ['Other current liability', 'Liability'],
        'other_noncurrent_liability' => ['Other non-current liability', 'Liability'],
        'capital' => ['Capital', 'Equity'],
        'retained_earnings' => ['Retained / accumulated earnings', 'Equity'],
        'other_equity' => ['Other equity', 'Equity'],
        'contra_equity' => ['Contra-equity', 'Equity'],
        'sales' => ['Sales', 'Revenue'],
        'contra_revenue' => ['Contra-revenue', 'Revenue'],
        'other_income' => ['Other income', 'Revenue'],
        'cost_of_goods_sold' => ['Cost of goods sold (Expense)', 'Expense'],
        'operating_expense' => ['Operating expense', 'Expense'],
        'other_expense' => ['Other expense', 'Expense'],
    ];

    private const NORMAL_BALANCES_BY_TYPE = [
        'Asset' => 'debit',
        'Liability' => 'credit',
        'Equity' => 'credit',
        'Revenue' => 'credit',
        'Expense' => 'debit',
    ];

    private const CONTRA_NORMAL_BALANCES = [
        'contra_asset' => 'credit',
        'contra_equity' => 'debit',
        'contra_revenue' => 'debit',
    ];

    public static function classifications(): array
    {
        return self::CLASSIFICATIONS;
    }

    public static function types(): array
    {
        return array_values(array_unique(array_column(self::CLASSIFICATIONS, 1)));
    }

    public static function supportsNormalBalance(string $code, string $classification, string $normalBalance): bool
    {
        $type = self::CLASSIFICATIONS[$classification][1] ?? null;
        if ($normalBalance === 'variable') {
            return $code === '3040' && $classification === 'other_equity';
        }

        if ($type === null || ! in_array($normalBalance, ['debit', 'credit'], true)) {
            return false;
        }

        if (self::NORMAL_BALANCES_BY_TYPE[$type] === $normalBalance) {
            return true;
        }

        return (self::CONTRA_NORMAL_BALANCES[$classification] ?? null) === $normalBalance;
    }

    protected static function booted(): void
    {
        static::saving(function (AccountingAccount $account): void {
            if (! isset(self::CLASSIFICATIONS[$account->classification])
                || self::CLASSIFICATIONS[$account->classification][1] !== $account->type
                || ! self::supportsNormalBalance($account->code, $account->classification, $account->normal_balance)) {
                throw new DomainException('Account type, classification, or normal balance is unsupported.');
            }

            if ($account->exists && $account->getOriginal('used_at') !== null
                && $account->isDirty(['code', 'type', 'classification', 'normal_balance'])) {
                throw new DomainException('Used account identity and normal balance are historically immutable.');
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
        $this->forceFill(['used_at' => $this->used_at ?? now('UTC')])->save();
    }

    public function hasPendingPostingPolicy(): bool
    {
        return in_array($this->code, self::POSTING_POLICY_HELD_CODES, true);
    }

    public function displayType(): string
    {
        return match (true) {
            $this->classification === 'contra_revenue' => 'Contra-Revenue',
            $this->classification === 'cost_of_goods_sold' => 'Cost of Sales',
            $this->classification === 'operating_expense' => $this->code === '6010'
                ? 'Cost of Sales / Expense'
                : 'Operating Expense',
            default => $this->type,
        };
    }

    public function isApprovedForPosting(): bool
    {
        return $this->code !== '6190'
            && ! $this->hasPendingPostingPolicy()
            && $this->is_active
            && $this->approved_at !== null
            && (self::CLASSIFICATIONS[$this->classification][1] ?? null) === $this->type
            && self::supportsNormalBalance($this->code, $this->classification, $this->normal_balance);
    }
}
