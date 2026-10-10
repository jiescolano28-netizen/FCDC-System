<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\AccountingPostingPeriod;
use App\Models\AccountingYtdSummary;
use App\Models\Inventory;
use App\Models\OpeningInventoryValuation;
use App\Models\StockMovement;
use App\Models\SupplierOpeningInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningBooksService
{
    public function saveOpening(string $date, array $lines, int $actorId): AccountingJournal
    {
        $date = $this->validBusinessDate($date);
        $validatedLines = $this->validatedOpeningLines($lines);
        $year = (int) substr($date, 0, 4);

        return DB::transaction(function () use ($date, $validatedLines, $actorId, $year): AccountingJournal {
            $period = AccountingPostingPeriod::firstOrCreate(
                ['book_key' => 'FCDC', 'fiscal_year' => $year],
                ['starts_on' => "$year-01-01", 'ends_on' => "$year-12-31", 'status' => 'open'],
            );
            $journal = AccountingJournal::where('source_type', 'opening')
                ->where('source_id', 'FCDC')
                ->lockForUpdate()
                ->first();
            if ($journal && $journal->status !== 'draft') {
                throw ValidationException::withMessages(['opening' => 'An approved opening journal already exists.']);
            }
            $this->assertInventoryOpeningMatches($validatedLines, $date, false);
            $journal ??= new AccountingJournal(['source_type' => 'opening', 'source_id' => 'FCDC']);
            $journal->fill([
                'book_key' => 'FCDC',
                'reference' => 'OPENING-FCDC-'.$year,
                'accounting_date' => $date,
                'posting_period_id' => $period->id,
                'description' => 'Approved opening balances at accounting cutover',
                'status' => 'draft',
                'prepared_by' => $actorId,
            ])->save();
            $journal->lines()->delete();
            foreach ($validatedLines as $line) {
                $journal->lines()->create($line);
            }

            return $journal->load('lines.account');
        });
    }
    public function saveInventoryValuation(string $date, string $evidence, array $lines, int $actorId): OpeningInventoryValuation
    {
        $date = $this->validBusinessDate($date);
        $evidence = trim($evidence);
        if ($evidence === '' || strlen($evidence) > 255) {
            throw ValidationException::withMessages(['inventoryEvidence' => 'Provide a supporting cutover count/value reference of at most 255 characters.']);
        }
        return DB::transaction(function () use ($date, $evidence, $lines, $actorId): OpeningInventoryValuation {
            $journal = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->lockForUpdate()->first();
            if ($journal?->status === 'posted') {
                throw ValidationException::withMessages(['inventoryLines' => 'Approved opening inventory is immutable; use a current-period correction.']);
            }
            $schedule = OpeningInventoryValuation::where('book_key', 'FCDC')->lockForUpdate()->first();
            $validatedLines = $this->validatedInventoryLines($date, $lines);
            $schedule ??= new OpeningInventoryValuation(['book_key' => 'FCDC']);
            $schedule->fill([
                'cutover_date' => $date,
                'evidence_reference' => $evidence,
                'status' => 'draft',
                'prepared_by' => $actorId,
                'approved_by' => null,
                'approved_at' => null,
            ])->save();
            $schedule->lines()->delete();
            foreach ($validatedLines as $line) {
                $schedule->lines()->create([
                    'inventory_id' => $line['inventory_id'],
                    'quantity' => $this->decimalFromHundredths($line['quantity_hundredths']),
                    'carrying_value_cents' => $line['carrying_value_cents'],
                ]);
            }

            return $schedule->load('lines.inventory');
        });
    }

    public function approveInventoryValuation(int $actorId): OpeningInventoryValuation
    {
        return DB::transaction(function () use ($actorId): OpeningInventoryValuation {
            $schedule = OpeningInventoryValuation::where('book_key', 'FCDC')
                ->lockForUpdate()
                ->with('lines.inventory')
                ->firstOrFail();
            $journal = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->lockForUpdate()->first();
            if ($journal?->status === 'posted') {
                throw ValidationException::withMessages(['inventoryLines' => 'Approved opening inventory is immutable; use a current-period correction.']);
            }
            if ($schedule->status !== 'draft') {
                throw ValidationException::withMessages(['inventoryLines' => 'This opening inventory valuation is already approved; save a revised schedule for review.']);
            }
            $this->validatedInventoryLines($schedule->cutover_date->toDateString(), $this->inventoryScheduleLines($schedule));
            $schedule->forceFill([
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => now('UTC'),
            ])->save();

            return $schedule->refresh()->load('lines.inventory');
        });
    }


    public function approveOpening(int $actorId): AccountingJournal
    {
        return DB::transaction(function () use ($actorId): AccountingJournal {
            $journal = AccountingJournal::where('source_type', 'opening')
                ->where('source_id', 'FCDC')
                ->lockForUpdate()
                ->with('lines.account')
                ->firstOrFail();
            if ($journal->status !== 'draft') {
                throw ValidationException::withMessages(['opening' => 'This opening journal is already approved and immutable.']);
            }
            $this->assertBalancedLines($journal->lines->map(fn ($line) => [
                'accountId' => $line->accounting_account_id,
                'debitCents' => $line->debit_cents,
                'creditCents' => $line->credit_cents,
            ])->all());

            foreach ($journal->lines as $line) {
                $account = $line->account;
                if (! $account?->isApprovedForPosting()) {
                    throw ValidationException::withMessages(['opening' => 'Every opening account must be active and approved.']);
                }
                if ($account->classification === 'inventory' && $line->credit_cents > 0) {
                    throw ValidationException::withMessages(['opening' => 'Opening Inventory must be a debit matching the approved item schedule.']);
                }
            }
            $this->assertInventoryOpeningMatches(
                $journal->lines->map(fn ($line) => [
                    'accounting_account_id' => $line->accounting_account_id,
                    'debit_cents' => $line->debit_cents,
                    'credit_cents' => $line->credit_cents,
                ])->all(),
                $journal->accounting_date->toDateString(),
                true,
            );

            app(SupplierPayablesService::class)->postOpeningSchedule($journal, $actorId);

            $journal->forceFill([
                'status' => 'posted',
                'posted_at' => now('UTC'),
                'posted_by' => $actorId,
                'approved_at' => now('UTC'),
                'approved_by' => $actorId,
            ])->save();
            foreach ($journal->lines as $line) {
                $line->account->markUsed();
            }

            return $journal->refresh()->load('lines.account');
        });
    }

    public function saveYtdSummary(int $year, string $throughDate, string $evidence, array $lines, int $actorId): AccountingYtdSummary
    {
        $throughDate = $this->validBusinessDate($throughDate);
        if ((int) substr($throughDate, 0, 4) !== $year || trim($evidence) === '') {
            throw ValidationException::withMessages(['ytdLines' => 'YTD evidence must identify a date and supporting schedule in the cutover fiscal year.']);
        }
        $amounts = $this->validatedYtdLines($lines);

        return DB::transaction(function () use ($year, $throughDate, $evidence, $amounts, $actorId): AccountingYtdSummary {
            $summary = AccountingYtdSummary::firstOrNew(['book_key' => 'FCDC', 'fiscal_year' => $year]);
            if ($summary->exists && $summary->status !== 'draft') {
                throw ValidationException::withMessages(['ytdLines' => 'An approved YTD summary is immutable.']);
            }
            $summary->fill([
                'through_date' => $throughDate,
                'evidence_reference' => trim($evidence),
                'status' => 'draft',
                'prepared_by' => $actorId,
            ])->save();
            $summary->lines()->delete();
            foreach ($amounts as $line) {
                $summary->lines()->create($line);
            }

            return $summary->load('lines.account');
        });
    }

    public function approveYtdSummary(int $actorId): AccountingYtdSummary
    {
        return DB::transaction(function () use ($actorId): AccountingYtdSummary {
            $journal = AccountingJournal::where('source_type', 'opening')
                ->where('source_id', 'FCDC')
                ->where('status', 'posted')
                ->firstOrFail();
            $cutover = CarbonImmutable::parse($journal->accounting_date, 'Asia/Manila');
            $summary = AccountingYtdSummary::where('book_key', 'FCDC')
                ->where('fiscal_year', $cutover->year)
                ->lockForUpdate()
                ->with('lines.account')
                ->firstOrFail();
            $through = CarbonImmutable::parse($summary->through_date, 'Asia/Manila');
            if ($summary->status !== 'draft' || $cutover->toDateString() === $cutover->startOfYear()->toDateString()
                || $through->toDateString() !== $cutover->subDay()->toDateString()) {
                throw ValidationException::withMessages(['ytdLines' => 'A non-January 1 cutover requires an unapproved YTD summary through the day before cutover.']);
            }
            if ($summary->lines->isEmpty() || $summary->evidence_reference === '') {
                throw ValidationException::withMessages(['ytdLines' => 'YTD approval requires non-empty supported income/expense balances.']);
            }
            foreach ($summary->lines as $line) {
                if (! $line->account?->isApprovedForPosting() || ! in_array($line->account->type, ['Revenue', 'Expense'], true)) {
                    throw ValidationException::withMessages(['ytdLines' => 'YTD summaries may use only active approved income and expense accounts.']);
                }
                $line->account->markUsed();
            }
            $summary->forceFill(['status' => 'approved', 'approved_at' => now('UTC'), 'approved_by' => $actorId])->save();

            return $summary->refresh()->load('lines.account');
        });
    }

    public function readiness(): array
    {
        $openingJournal = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->first();
        $journal = $openingJournal?->status === 'posted' ? $openingJournal : null;
        $cutoverDate = $openingJournal?->accounting_date?->toDateString();
        $year = $cutoverDate ? (int) substr($cutoverDate, 0, 4) : now('Asia/Manila')->year;
        $midyear = $cutoverDate !== null && $cutoverDate !== "$year-01-01";
        $chartApproved = AccountingAccount::where('is_active', true)->exists()
            && ! AccountingAccount::where('is_active', true)->whereNull('approved_at')->exists();
        $requiredMappings = [...array_keys(AccountingPostingMapping::REQUIRED_CLASSIFICATIONS), 'recovery_offset', 'adjustment'];
        $mappings = AccountingPostingMapping::whereIn('source', $requiredMappings)->with('account')->get()->keyBy('source');
        $mappingsApproved = collect($requiredMappings)->every(fn (string $source) => $mappings->get($source)?->isApprovedForPosting() === true);
        $ytdApproved = AccountingYtdSummary::where('book_key', 'FCDC')->where('fiscal_year', $year)->where('status', 'approved')->exists();
        $postedSupplierInvoices = SupplierOpeningInvoice::activePosted()->get();
        $apLines = $journal ? $journal->lines()->with('account')->get()->filter(
            fn ($line) => $line->account?->classification === 'accounts_payable',
        ) : collect();
        $apScheduleMatches = $apLines->sum('credit_cents') === $postedSupplierInvoices->sum('amount_cents')
            && $apLines->sum('debit_cents') === 0;

        return [
            'cutover' => $journal ? 'Approved' : 'Cutover approval required',
            'chart' => $chartApproved ? 'Approved chart available' : 'Chart approval required',
            'mappings' => $mappingsApproved ? 'Posting mappings approved' : 'Posting mapping approvals required',
            'opening' => $journal ? 'Balanced opening journal approved' : 'Opening journal approval required',
            'supplier' => $journal
                ? ($apScheduleMatches ? 'Opening supplier schedule reconciled' : 'Opening supplier schedule mismatch')
                : (SupplierOpeningInvoice::where('status', 'draft')->whereNull('reversal_of_id')->exists()
                    ? 'Opening supplier schedule pending approval'
                    : 'Opening supplier schedule required'),
            'inventory' => $this->inventoryReadiness($journal),
            'valuation' => 'Valuation policies not approved',
            'ytd' => $cutoverDate === null
                ? 'Save a cutover date to determine YTD evidence requirements'
                : (! $midyear
                    ? 'Not required for January 1 cutover'
                    : ($ytdApproved ? 'Approved pre-cutover YTD evidence' : 'Pre-cutover YTD evidence required')),
            'production' => 'Production activation unavailable: valuation policies remain outstanding; chart, mapping, cutover, supplier, inventory and YTD readiness are listed above.',
        ];
    }

    private function validatedOpeningLines(array $lines): array
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => 'Opening journal requires at least two non-empty lines.']);
        }
        $result = [];
        foreach ($lines as $index => $line) {
            $accountId = filter_var($line['accountId'] ?? null, FILTER_VALIDATE_INT);
            $account = $accountId ? AccountingAccount::find($accountId) : null;
            if (! $account?->isApprovedForPosting()) {
                throw ValidationException::withMessages(["lines.$index.accountId" => 'Select an active approved account.']);
            }
            if ($account->classification === 'inventory' && $this->amountInCents($line['credit'] ?? '') > 0) {
                throw ValidationException::withMessages(["lines.$index" => 'Opening Inventory must be a debit supported by the approved item schedule.']);
            }
            $debit = $this->amountInCents($line['debit'] ?? '');
            $credit = $this->amountInCents($line['credit'] ?? '');
            if (($debit > 0) === ($credit > 0)) {
                throw ValidationException::withMessages(["lines.$index" => 'Each opening line must contain exactly one positive debit or credit amount.']);
            }
            $result[] = ['accounting_account_id' => $account->id, 'debit_cents' => $debit, 'credit_cents' => $credit];
        }
        $this->assertBalancedLines(array_map(fn ($line) => [
            'accountId' => $line['accounting_account_id'],
            'debitCents' => $line['debit_cents'],
            'creditCents' => $line['credit_cents'],
        ], $result));

        return $result;
    }

    private function validatedInventoryLines(string $date, array $lines): array
    {
        $items = Inventory::query()->orderBy('id')->lockForUpdate()->get(['id']);
        if (count($lines) !== $items->count()) {
            throw ValidationException::withMessages(['inventoryLines' => 'The opening valuation must include every inventory item exactly once.']);
        }
        $expectedIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();
        $result = [];
        $seen = [];
        foreach ($lines as $index => $line) {
            $id = filter_var($line['inventoryId'] ?? null, FILTER_VALIDATE_INT);
            if (! $id || ! in_array($id, $expectedIds, true) || isset($seen[$id])) {
                throw ValidationException::withMessages(["inventoryLines.$index.inventoryId" => 'Select each existing inventory item once.']);
            }
            $seen[$id] = true;
            $quantity = $this->quantityInHundredths($line['quantity'] ?? null);
            $value = $this->amountInCents($line['value'] ?? null);
            if (($quantity === 0) !== ($value === 0)) {
                throw ValidationException::withMessages(["inventoryLines.$index" => 'A zero quantity requires zero carrying value, and positive stock requires positive supported value.']);
            }
            $timelineQuantity = 0;
            foreach (StockMovement::where('inventory_id', $id)->whereDate('effective_date', '<=', $date)->get(['quantity']) as $movement) {
                $timelineQuantity += $this->quantityInHundredths($movement->quantity, true);
            }
            if ($quantity !== $timelineQuantity || $quantity < 0) {
                throw ValidationException::withMessages(["inventoryLines.$index.quantity" => 'Approved opening quantity must equal the non-negative stock timeline balance at cutover.']);
            }
            $result[] = [
                'inventory_id' => (int) $id,
                'quantity_hundredths' => $quantity,
                'carrying_value_cents' => $value,
            ];
        }
        if (count($seen) !== count($expectedIds)) {
            throw ValidationException::withMessages(['inventoryLines' => 'The opening valuation must include every inventory item exactly once.']);
        }

        return $result;
    }

    private function assertInventoryOpeningMatches(array $journalLines, string $date, bool $requireApproved): void
    {
        $inventoryAccountIds = AccountingAccount::where('classification', 'inventory')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $inventoryLines = collect($journalLines)->filter(function (array $line) use ($inventoryAccountIds): bool {
            $accountId = (int) ($line['accounting_account_id'] ?? $line['accountId'] ?? 0);
            return in_array($accountId, $inventoryAccountIds, true);
        });
        $schedule = OpeningInventoryValuation::where('book_key', 'FCDC')->with('lines')->first();
        if ($inventoryLines->isEmpty() && ! $schedule) {
            return;
        }
        if (! $schedule || $schedule->cutover_date->toDateString() !== $date
            || ($requireApproved && $schedule->status !== 'approved')) {
            throw ValidationException::withMessages(['opening' => 'Opening Inventory requires a reviewed per-item valuation schedule for the same cutover date.']);
        }
        $scheduleLines = $this->validatedInventoryLines($date, $this->inventoryScheduleLines($schedule));
        $scheduleTotal = 0;
        foreach ($scheduleLines as $line) {
            if ($line['carrying_value_cents'] > PHP_INT_MAX - $scheduleTotal) {
                throw ValidationException::withMessages(['inventoryLines' => 'Opening Inventory total exceeds supported PHP centavos.']);
            }
            $scheduleTotal += $line['carrying_value_cents'];
        }
        $journalTotal = 0;
        foreach ($inventoryLines as $line) {
            $debit = (int) ($line['debit_cents'] ?? $line['debitCents'] ?? 0);
            $credit = (int) ($line['credit_cents'] ?? $line['creditCents'] ?? 0);
            if ($credit > 0 || $debit > PHP_INT_MAX - $journalTotal) {
                throw ValidationException::withMessages(['opening' => 'Opening Inventory must be a non-negative debit equal to the item valuation schedule.']);
            }
            $journalTotal += $debit;
        }
        if ($journalTotal !== $scheduleTotal) {
            throw ValidationException::withMessages(['opening' => 'Opening Inventory debit must exactly match the approved per-item carrying values.']);
        }
    }

    private function inventoryReadiness(?AccountingJournal $journal): string
    {
        $schedule = OpeningInventoryValuation::where('book_key', 'FCDC')->with('lines')->first();
        if (! $schedule) {
            return 'Opening inventory valuation schedule required';
        }
        if ($schedule->status !== 'approved') {
            return 'Opening inventory valuation pending approval';
        }
        if (! $journal) {
            return 'Approved item valuation pending opening Inventory reconciliation';
        }
        try {
            $this->assertInventoryOpeningMatches(
                $journal->lines()->with('account')->get()->map(fn ($line) => [
                    'accounting_account_id' => $line->accounting_account_id,
                    'debit_cents' => $line->debit_cents,
                    'credit_cents' => $line->credit_cents,
                ])->all(),
                $journal->accounting_date->toDateString(),
                true,
            );
        } catch (ValidationException) {
            return 'Opening inventory schedule mismatch';
        }

        return 'Opening stock schedule reconciled';
    }
    private function inventoryScheduleLines(OpeningInventoryValuation $schedule): array
    {
        return $schedule->lines->map(fn ($line) => [
            'inventoryId' => (string) $line->inventory_id,
            'quantity' => $line->quantity,
            'value' => $this->decimalFromHundredths((int) $line->carrying_value_cents),
        ])->all();
    }


    private function quantityInHundredths(mixed $quantity, bool $allowNegative = false): int
    {
        if (! is_string($quantity) && ! is_int($quantity)) {
            throw ValidationException::withMessages(['inventoryLines' => 'Quantities must be non-negative with at most two decimal places.']);
        }
        $quantity = trim((string) $quantity);
        $pattern = $allowNegative ? '/^-?(?:0|[1-9]\\d*)(?:\\.(\\d{1,2}))?$/D' : '/^(?:0|[1-9]\\d*)(?:\\.(\\d{1,2}))?$/D';
        if (! preg_match($pattern, $quantity)) {
            throw ValidationException::withMessages(['inventoryLines' => 'Quantities must be non-negative with at most two decimal places.']);
        }
        $negative = str_starts_with($quantity, '-');
        $quantity = ltrim($quantity, '-');
        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
        $hundredths = ltrim($whole.str_pad($fraction, 2, '0'), '0') ?: '0';
        if (strlen($hundredths) > 12) {
            throw ValidationException::withMessages(['inventoryLines' => 'Quantity exceeds the supported inventory precision.']);
        }
        $value = (int) $hundredths;

        return $negative && $value !== 0 ? -$value : $value;
    }

    private function decimalFromHundredths(int $hundredths): string
    {
        return intdiv($hundredths, 100).'.'.str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT);
    }


    private function validatedYtdLines(array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['ytdLines' => 'YTD summary requires supported income or expense balances.']);
        }
        $result = [];
        foreach ($lines as $index => $line) {
            $accountId = filter_var($line['accountId'] ?? null, FILTER_VALIDATE_INT);
            $account = $accountId ? AccountingAccount::find($accountId) : null;
            if (! $account?->isApprovedForPosting() || ! in_array($account->type, ['Revenue', 'Expense'], true)) {
                throw ValidationException::withMessages(["ytdLines.$index.accountId" => 'Select an active approved Revenue or Expense account.']);
            }
            $amount = $this->amountInCents($line['amount'] ?? '');
            if ($amount <= 0) {
                throw ValidationException::withMessages(["ytdLines.$index.amount" => 'YTD amounts must be positive PHP centavos.']);
            }
            $result[] = ['accounting_account_id' => $account->id, 'amount_cents' => $amount];
        }

        return $result;
    }

    private function assertBalancedLines(array $lines): void
    {
        $debits = 0;
        $credits = 0;
        foreach ($lines as $line) {
            $debit = (int) $line['debitCents'];
            $credit = (int) $line['creditCents'];
            if ($debit > PHP_INT_MAX - $debits || $credit > PHP_INT_MAX - $credits) {
                throw ValidationException::withMessages(['lines' => 'Opening journal total exceeds the supported PHP centavo range.']);
            }
            $debits += $debit;
            $credits += $credit;
        }
        if (count($lines) < 2 || $debits <= 0 || $debits !== $credits) {
            throw ValidationException::withMessages(['lines' => 'Opening journal must have positive, exactly equal debit and credit totals.']);
        }
    }

    private function amountInCents(mixed $amount): int
    {
        if ($amount === '' || $amount === null) {
            return 0;
        }
        if (! is_string($amount) && ! is_int($amount)) {
            throw ValidationException::withMessages(['lines' => 'Amounts must be non-negative PHP values with at most two decimal places.']);
        }
        $amount = trim((string) $amount);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.(\d{1,2}))?$/D', $amount)) {
            throw ValidationException::withMessages(['lines' => 'Amounts must be non-negative PHP values with at most two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $cents = ltrim($whole.str_pad($fraction, 2, '0'), '0') ?: '0';
        $maximumCents = (string) PHP_INT_MAX;
        if (strlen($cents) > strlen($maximumCents)
            || (strlen($cents) === strlen($maximumCents) && strcmp($cents, $maximumCents) > 0)) {
            throw ValidationException::withMessages(['lines' => 'Amount exceeds the supported PHP centavo range.']);
        }

        return (int) $cents;
    }

    private function validBusinessDate(string $date): string
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');
        } catch (\Throwable) {
            $parsed = false;
        }
        if (! $parsed || $parsed->format('Y-m-d') !== $date || $parsed->isFuture()) {
            throw ValidationException::withMessages(['cutoverDate' => 'Cutover date must be a valid, non-future Manila business date.']);
        }

        return $parsed->toDateString();
    }
}
