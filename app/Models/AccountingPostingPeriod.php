<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingPostingPeriod extends Model
{
    protected $fillable = ['book_key', 'fiscal_year', 'starts_on', 'ends_on', 'status'];

    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d', 'fiscal_year' => 'integer'];
    }

    public function journals(): HasMany
    {
        return $this->hasMany(AccountingJournal::class, 'posting_period_id');
    }
}
