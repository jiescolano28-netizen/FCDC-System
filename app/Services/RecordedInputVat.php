<?php

namespace App\Services;

use App\Models\ReworkingCostRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads saved reworking-cost input VAT for the VAT compliance screens.
 *
 * Reworking cost records are saved in the employee session by the inventory
 * module and mirrored to the database so tax-authorized users can reconcile
 * the same input VAT. Database and session copies are keyed by their source
 * record, so the same saved record is never counted twice.
 */
class RecordedInputVat
{
    public const SESSION_KEY_PREFIX = 'demo.reworking-cost-records.employee.';

    public function forDateRange(string $startDate, string $endDate): array
    {
        return $this->summarize($this->recordsForDateRange($startDate, $endDate));
    }

    public function recordsForDateRange(string $startDate, string $endDate, string $search = ''): Collection
    {
        $start = CarbonImmutable::parse($startDate, 'Asia/Manila')->startOfDay();
        $end = CarbonImmutable::parse($endDate, 'Asia/Manila')->startOfDay();

        abort_unless($end->greaterThanOrEqualTo($start), 404);

        $search = Str::lower(trim($search));

        return $this->storedRecords()
            ->filter(function (array $record) use ($start, $end): bool {
                $date = CarbonImmutable::parse($record['date'], 'Asia/Manila')->startOfDay();

                return $date->greaterThanOrEqualTo($start) && $date->lessThanOrEqualTo($end);
            })
            ->when($search !== '', fn (Collection $records) => $records->filter(
                fn (array $record): bool => Str::contains(
                    Str::lower(implode(' ', [
                        $record['batch_number'],
                        $record['project'],
                        $record['description'],
                        $record['date'],
                    ])),
                    $search,
                ),
            ))
            ->sortBy(fn (array $record): string => $record['date'].'-'.str_pad((string) $record['source_record_id'], 10, '0', STR_PAD_LEFT))
            ->values();
    }

    public function summarize(Collection $records): array
    {
        $inputVatCents = 0;
        $reworkingCostCents = 0;

        foreach ($records as $record) {
            $inputVatCents += (int) round((float) $record['input_vat'] * 100);
            $reworkingCostCents += (int) round((float) $record['total_reworking_cost'] * 100);
        }

        return [
            'record_count' => $records->count(),
            'batch_count' => $records->pluck('batch_number')->unique()->count(),
            'input_vat' => $this->formatCents($inputVatCents),
            'total_reworking_cost' => $this->formatCents($reworkingCostCents),
        ];
    }

    /**
     * Earliest and latest record dates in Manila, or null when none exist.
     *
     * @return array{0: string, 1: string}|null
     */
    public function dateBounds(): ?array
    {
        $records = $this->storedRecords();

        if ($records->isEmpty()) {
            return null;
        }

        return [
            $records->min('date'),
            $records->max('date'),
        ];
    }

    private function storedRecords(): Collection
    {
        $records = $this->databaseRecords()->keyBy('source_key');

        // The source-keyed table is authoritative so every tax-authorized user
        // sees the same saved records. Session data is only a fallback for
        // records saved before the table was available; the source key keeps
        // those legacy copies from being counted twice.
        foreach ($this->sessionRecords() as $record) {
            if (! $records->has($record['source_key'])) {
                $records->put($record['source_key'], $record);
            }
        }

        return $records->values();
    }

    private function databaseRecords(): Collection
    {
        try {
            if (! Schema::hasTable('reworking_cost_records')) {
                return collect();
            }

            return ReworkingCostRecord::query()
                ->orderBy('record_date')
                ->orderBy('id')
                ->get()
                ->map(fn (ReworkingCostRecord $record): array => [
                    'source_key' => 'reworking:'.($record->employee_id ?? 0).':'.$record->source_record_id,
                    'source_record_id' => (int) $record->source_record_id,
                    'employee_id' => $record->employee_id,
                    'project_id' => $record->project_id,
                    'project' => $record->project_name,
                    'batch_number' => $record->batch_number,
                    'date' => $record->record_date->format('Y-m-d'),
                    'description' => $record->description,
                    'total_reworking_cost' => (string) $record->total_reworking_cost,
                    'input_vat' => (string) $record->input_vat,
                    'attachment' => $record->attachment,
                ]);
        } catch (Throwable) {
            return collect();
        }
    }

    private function sessionRecords(): Collection
    {
        // Laravel's session store expands dotted keys into nested arrays, so
        // the canonical path must be read with data_get(). Older payloads may
        // still contain a literal top-level key, so support both shapes.
        $stored = session()->all();
        $employees = data_get($stored, rtrim(self::SESSION_KEY_PREFIX, '.'), []);

        if (! is_array($employees)) {
            $employees = [];
        }

        foreach ($stored as $key => $records) {
            if (is_string($key) && str_starts_with($key, self::SESSION_KEY_PREFIX) && is_array($records)) {
                $employees[(int) substr($key, strlen(self::SESSION_KEY_PREFIX))] = $records;
            }
        }

        $sessionRecords = collect($employees)
            ->filter(fn ($records, $employeeId): bool => is_numeric($employeeId) && is_array($records))
            ->flatMap(function (array $records, $employeeId): array {
                $employeeId = (int) $employeeId;

                return collect($records)
                    ->filter(fn ($record): bool => is_array($record) && isset($record['id'], $record['date']))
                    ->map(fn (array $record): array => [
                        'source_key' => 'reworking:'.$employeeId.':'.(int) $record['id'],
                        'source_record_id' => (int) $record['id'],
                        'employee_id' => $employeeId,
                        'project_id' => $record['project_id'] ?? null,
                        'project' => (string) ($record['project'] ?? ''),
                        'batch_number' => (string) ($record['batch_number'] ?? ''),
                        'date' => CarbonImmutable::parse($record['date'], 'Asia/Manila')->toDateString(),
                        'description' => (string) ($record['description'] ?? ''),
                        'total_reworking_cost' => (string) ($record['total_reworking_cost'] ?? '0.00'),
                        'input_vat' => (string) ($record['input_vat'] ?? '0.00'),
                        'attachment' => $record['attachment'] ?? null,
                    ])
                    ->all();
            })
            ->values();

        return $sessionRecords;
    }

    private function formatCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
