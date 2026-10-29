<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\AccountingPostingPeriod;
use App\Models\OperatingCostRecord;
use App\Models\ReworkingCostRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Generates posted accounting journals from persisted operating sources.
 *
 * POS sales post automatically at checkout through PosSalePostingService, and
 * validated Operating Cost Records post automatically when approved accounts
 * are configured. This service also supports explicit generation for source
 * registers such as Reworking Cost Records and for operating costs saved before
 * accounting configuration. Each source row can produce at most one journal
 * because source_type and source_id are unique and checked before insertion.
 */
class JournalSourcePostingService
{
    public const SOURCE_REWORKING_COST = 'reworking_cost';

    public const SOURCE_OPERATING_COST = 'operating_cost';

    /**
     * Official operating expense account codes by recorded category.
     *
     * @var array<string, string>
     */
    private const OPERATING_ACCOUNT_CODES = [
        'Rent Expense' => '6120',
        'Utilities Expense' => '6130',
        'Repairs and Maintenance Expense' => '6140',
        'Office Supplies Expense' => '6150',
        'Cleaning Supplies Expense' => '6160',
        'Transportation and Delivery Expense' => '6170',
        'Other Operating Expenses' => '6180',
    ];

    public function generate(string $sourceType, int $sourceId, int $actorId): AccountingJournal
    {
        return match ($sourceType) {
            self::SOURCE_REWORKING_COST => $this->postReworkingCost($sourceId, $actorId),
            self::SOURCE_OPERATING_COST => $this->postOperatingCost($sourceId, $actorId),
            default => throw ValidationException::withMessages([
                'journal' => 'Unsupported automatic journal source.',
            ]),
        };
    }

    public function postReworkingCost(int $recordId, int $actorId): AccountingJournal
    {
        return DB::transaction(function () use ($recordId, $actorId): AccountingJournal {
            $record = ReworkingCostRecord::query()->lockForUpdate()->findOrFail($recordId);
            $existing = $this->existingJournal(self::SOURCE_REWORKING_COST, $record->id);
            if ($existing) {
                return $existing;
            }

            $date = $record->record_date?->toDateString();
            if (! $date) {
                throw ValidationException::withMessages(['journal' => 'A reworking cost record needs a recorded date before it can be posted.']);
            }
            $period = $this->openPeriodForDate($date);
            $netCents = $this->cents((string) $record->total_reworking_cost);
            $vatCents = $this->cents((string) ($record->input_vat ?? '0'));
            if ($netCents < 0 || $vatCents < 0 || $netCents + $vatCents < 1) {
                throw ValidationException::withMessages(['journal' => 'A reworking cost record needs a positive recorded cost or input VAT before it can be posted.']);
            }

            $lines = [];
            if ($netCents > 0) {
                $lines[] = ['account' => $this->accountForSource('recovery_offset', '6010', 'reworking cost'), 'debit' => $netCents, 'credit' => 0];
            }
            if ($vatCents > 0) {
                $lines[] = ['account' => $this->accountByCode('1030', 'input VAT'), 'debit' => $vatCents, 'credit' => 0];
            }
            $lines[] = [
                'account' => $this->settlementAccount($record->payment_method, 'reworking cost record'),
                'debit' => 0,
                'credit' => $netCents + $vatCents,
            ];

            $description = trim('Reworking cost '.$record->batch_number
                .($record->description ? ' — '.$record->description : ''));

            return $this->createPostedJournal(
                self::SOURCE_REWORKING_COST,
                (string) $record->id,
                'RC-'.$record->id,
                $date,
                $period,
                $description,
                $record->batch_number,
                $actorId,
                $lines,
            );
        });
    }

    public function postOperatingCost(int $recordId, int $actorId): AccountingJournal
    {
        return DB::transaction(function () use ($recordId, $actorId): AccountingJournal {
            $record = OperatingCostRecord::query()->lockForUpdate()->findOrFail($recordId);
            $existing = $this->existingJournal(self::SOURCE_OPERATING_COST, $record->id);
            if ($existing) {
                return $existing;
            }

            $date = $record->expense_date?->toDateString();
            if (! $date) {
                throw ValidationException::withMessages(['journal' => 'An operating cost record needs a recorded expense date before it can be posted.']);
            }
            $period = $this->openPeriodForDate($date);
            $amountCents = $this->cents((string) $record->amount);
            if ($amountCents < 1) {
                throw ValidationException::withMessages(['journal' => 'An operating cost record needs a positive recorded amount before it can be posted.']);
            }

            $code = self::OPERATING_ACCOUNT_CODES[$record->expense_category] ?? null;
            if ($code === null) {
                throw ValidationException::withMessages(['journal' => 'The operating expense category has no approved chart-of-accounts mapping.']);
            }

            $lines = [
                ['account' => $this->accountByCode($code, $record->expense_category), 'debit' => $amountCents, 'credit' => 0],
                [
                    'account' => $this->settlementAccount($record->payment_method, 'operating cost record'),
                    'debit' => 0,
                    'credit' => $amountCents,
                ],
            ];

            $description = trim($record->expense_category.' '.$record->reference_number
                .($record->description ? ' — '.$record->description : ''));

            return $this->createPostedJournal(
                self::SOURCE_OPERATING_COST,
                (string) $record->id,
                'OPEX-'.$record->id,
                $date,
                $period,
                $description,
                $record->reference_number,
                $actorId,
                $lines,
            );
        });
    }

    /**
     * Unposted source rows for the Journal Entry workspace.
     *
     * Eligibility is descriptive: the generate action still revalidates the
     * period, accounts, and payment method inside a transaction.
     *
     * @return array<string, array{label: string, records: array<int, array<string, mixed>>}>
     */
    public function pendingCandidates(): array
    {
        $postedReworking = AccountingJournal::query()
            ->where('source_type', self::SOURCE_REWORKING_COST)
            ->pluck('source_id')->map(fn ($id) => (string) $id)->all();
        $postedOperating = AccountingJournal::query()
            ->where('source_type', self::SOURCE_OPERATING_COST)
            ->pluck('source_id')->map(fn ($id) => (string) $id)->all();

        $reworking = ReworkingCostRecord::query()
            ->whereNotIn('id', array_map('intval', $postedReworking))
            ->orderByDesc('record_date')->orderByDesc('id')->limit(50)->get()
            ->map(function (ReworkingCostRecord $record): array {
                $net = $this->cents((string) $record->total_reworking_cost);
                $vat = $this->cents((string) ($record->input_vat ?? '0'));
                $reason = $this->ineligibleReason(
                    $record->record_date?->toDateString(),
                    $net + $vat,
                    $record->payment_method,
                    'payment method',
                );

                return [
                    'id' => $record->id,
                    'reference' => $record->batch_number,
                    'date' => $record->record_date?->toDateString(),
                    'description' => $record->description,
                    'amount_cents' => $net + $vat,
                    'eligible' => $reason === null,
                    'reason' => $reason,
                ];
            })->values()->all();

        $operating = OperatingCostRecord::query()
            ->whereNotIn('id', array_map('intval', $postedOperating))
            ->orderByDesc('expense_date')->orderByDesc('id')->limit(50)->get()
            ->map(function (OperatingCostRecord $record): array {
                $amount = $this->cents((string) $record->amount);
                $reason = $this->ineligibleReason(
                    $record->expense_date?->toDateString(),
                    $amount,
                    $record->payment_method,
                    'payment method',
                );
                if ($reason === null && ! isset(self::OPERATING_ACCOUNT_CODES[$record->expense_category])) {
                    $reason = 'The expense category has no mapped account.';
                }

                return [
                    'id' => $record->id,
                    'reference' => $record->reference_number,
                    'date' => $record->expense_date?->toDateString(),
                    'description' => $record->expense_category,
                    'amount_cents' => $amount,
                    'eligible' => $reason === null,
                    'reason' => $reason,
                ];
            })->values()->all();

        return [
            self::SOURCE_REWORKING_COST => ['label' => 'Reworking cost records', 'records' => $reworking],
            self::SOURCE_OPERATING_COST => ['label' => 'Operating cost records', 'records' => $operating],
        ];
    }

    private function existingJournal(string $sourceType, int $sourceId): ?AccountingJournal
    {
        return AccountingJournal::query()
            ->where('source_type', $sourceType)
            ->where('source_id', (string) $sourceId)
            ->lockForUpdate()
            ->first()?->load('lines.account');
    }

    private function createPostedJournal(
        string $sourceType,
        string $sourceId,
        string $reference,
        string $date,
        AccountingPostingPeriod $period,
        string $description,
        ?string $externalReference,
        int $actorId,
        array $lines,
    ): AccountingJournal {
        $reference = Str::limit($reference, 80, '');
        if (AccountingJournal::query()->where('reference', $reference)->exists()) {
            throw ValidationException::withMessages(['journal' => 'The generated journal reference is already in use.']);
        }
        $this->assertBalanced($lines);

        $journal = AccountingJournal::query()->create([
            'book_key' => 'FCDC',
            'reference' => $reference,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'accounting_date' => $date,
            'posting_period_id' => $period->id,
            'description' => Str::limit($description, 255, ''),
            'external_reference' => $externalReference ? Str::limit($externalReference, 120, '') : null,
            'status' => 'draft',
            'prepared_by' => $actorId,
        ]);

        foreach ($lines as $line) {
            $journal->lines()->create([
                'accounting_account_id' => $line['account']->id,
                'debit_cents' => $line['debit'],
                'credit_cents' => $line['credit'],
            ]);
            $line['account']->markUsed();
        }

        $journal->forceFill([
            'status' => 'posted',
            'posted_at' => now('UTC'),
            'posted_by' => $actorId,
        ])->save();

        return $journal->refresh()->load('lines.account');
    }

    private function accountForSource(string $source, string $fallbackCode, string $label): AccountingAccount
    {
        $mapping = AccountingPostingMapping::query()->where('source', $source)->with('account')->first();
        if ($mapping?->isApprovedForPosting()) {
            return AccountingAccount::query()->lockForUpdate()->findOrFail($mapping->accounting_account_id);
        }

        return $this->accountByCode($fallbackCode, $label);
    }

    private function accountByCode(string $code, string $label): AccountingAccount
    {
        $account = AccountingAccount::query()->where('code', $code)->lockForUpdate()->first();
        if (! $account?->isApprovedForPosting()) {
            throw ValidationException::withMessages([
                'journal' => "An active approved {$label} account ({$code}) is required before this source can be posted.",
            ]);
        }

        return $account;
    }

    private function settlementAccount(?string $method, string $context): AccountingAccount
    {
        if (trim((string) $method) === '') {
            throw ValidationException::withMessages([
                'payment_method' => "A recorded payment method is required before a {$context} journal can be generated.",
            ]);
        }

        $normalized = Str::of($method)->lower()->replace(['_', '-'], ' ')->squish()->value();

        if (Str::contains($normalized, ['gcash', 'maya', 'e wallet', 'ewallet'])) {
            throw ValidationException::withMessages([
                'payment_method' => 'This e-wallet payment method has no approved chart-of-accounts settlement mapping.',
            ]);
        }

        $source = match (true) {
            Str::contains($normalized, 'card') => 'card_clearing',
            Str::contains($normalized, 'cash') => 'cash',
            Str::contains($normalized, ['bank', 'transfer', 'cheque', 'check', 'deposit', 'remittance', 'online']) => 'bank',
            in_array($normalized, ['accounts payable', 'ap', 'credit', 'on account', 'unpaid', 'payable'], true) => 'accounts_payable',
            default => null,
        };

        if ($source === null) {
            throw ValidationException::withMessages([
                'payment_method' => "The recorded payment method \"{$method}\" has no approved cash, bank, card, or accounts-payable settlement mapping.",
            ]);
        }

        $fallback = match ($source) {
            'cash' => '1010',
            'bank', 'card_clearing' => '1020',
            'accounts_payable' => '2010',
            default => '1010',
        };

        return $this->accountForSource($source, $fallback, $source);
    }

    private function openPeriodForDate(string $date): AccountingPostingPeriod
    {
        $dateValue = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');
        if (! $dateValue || $dateValue->greaterThan(CarbonImmutable::now('Asia/Manila')->startOfDay())) {
            throw ValidationException::withMessages(['journal' => 'Completed source records cannot be future-dated for posting.']);
        }

        return app(AccountingPeriodService::class)->lockOpenPeriodForDate(
            $date,
            'journal',
            'The source date is not in an open accounting period.',
        );
    }

    private function ineligibleReason(?string $date, int $amountCents, ?string $paymentMethod, string $label): ?string
    {
        if (! $date) {
            return 'A recorded date is missing.';
        }
        $dateValue = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');
        if (! $dateValue) {
            return 'The recorded date is invalid.';
        }
        if ($dateValue->greaterThan(CarbonImmutable::now('Asia/Manila')->startOfDay())) {
            return 'Future-dated source records cannot be posted.';
        }
        if ($amountCents < 1) {
            return 'A positive recorded amount is required.';
        }
        if (trim((string) $paymentMethod) === '') {
            return 'A recorded '.$label.' is required.';
        }

        try {
            $this->settlementSource($paymentMethod);

            return null;
        } catch (ValidationException) {
            return 'The recorded '.$label.' has no approved settlement account mapping.';
        }
    }

    /**
     * Resolve only the settlement source without touching the database.
     */
    private function settlementSource(string $method): string
    {
        $normalized = Str::of($method)->lower()->replace(['_', '-'], ' ')->squish()->value();
        if (Str::contains($normalized, ['gcash', 'maya', 'e wallet', 'ewallet'])) {
            throw ValidationException::withMessages(['payment_method' => 'Unsupported e-wallet payment method.']);
        }

        return match (true) {
            Str::contains($normalized, 'card') => 'card_clearing',
            Str::contains($normalized, 'cash') => 'cash',
            Str::contains($normalized, ['bank', 'transfer', 'cheque', 'check', 'deposit', 'remittance', 'online']) => 'bank',
            in_array($normalized, ['accounts payable', 'ap', 'credit', 'on account', 'unpaid', 'payable'], true) => 'accounts_payable',
            default => throw ValidationException::withMessages(['payment_method' => 'Unsupported payment method.']),
        };
    }

    private function assertBalanced(array $lines): void
    {
        $debits = 0;
        $credits = 0;
        foreach ($lines as $line) {
            $debits += (int) $line['debit'];
            $credits += (int) $line['credit'];
        }
        if ($debits < 1 || $debits !== $credits) {
            throw ValidationException::withMessages(['journal' => 'The source journal must have equal positive debit and credit totals.']);
        }
    }

    private function cents(string $amount): int
    {
        $value = trim($amount);
        if ($value === '') {
            return 0;
        }
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages(['journal' => 'Recorded source amounts must be non-negative PHP values with no more than two decimal places.']);
        }

        return (int) round((float) $value * 100);
    }
}
