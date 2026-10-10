<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPostingMapping extends Model
{
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
        return $this->approved_at !== null && $this->account?->isApprovedForPosting() === true;
    }
}
