<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Supplier extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'legal_name', 'tax_identifier', 'address', 'contact_name', 'email', 'phone',
        'created_by', 'updated_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (Supplier $supplier): void {
            $supplier->code = mb_strtoupper(trim($supplier->code), 'UTF-8');
        });
        static::updating(function (Supplier $supplier): void {
            if ($supplier->isDirty('code')) {
                throw new DomainException('Supplier codes are stable and cannot be changed.');
            }
        });
        static::deleting(function (Supplier $supplier): void {
            if ($supplier->openingInvoices()->exists()) {
                throw new DomainException('A supplier with invoice history cannot be deleted.');
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'legal_name', 'tax_identifier', 'address', 'contact_name', 'email', 'phone'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        $search = trim($search);

        return $query->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
            $query->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('legal_name', 'like', "%{$search}%");
        }));
    }

    public function openingInvoices(): HasMany
    {
        return $this->hasMany(SupplierOpeningInvoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'updated_by');
    }
}
