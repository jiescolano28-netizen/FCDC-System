<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\AccountingPostingPeriod;
use App\Models\CashDisbursement;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\Supplier;
use App\Models\SupplierOpeningInvoice;
use App\Models\SupplierPurchaseInvoice;
use App\Services\Inventory\RecordValuedStockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CashDisbursementService
{
    public function saveDraft(array $input, int $actorId, ?int $disbursementId = null): CashDisbursement
    {
        $actor = Employee::query()->findOrFail($actorId);
        if (! $actor->can('accounting.prepare-disbursements')) {
            abort(403);
        }
        $validated = $this->validateDraft($input);

        return DB::transaction(function () use ($validated, $actorId, $disbursementId): CashDisbursement {
            $disbursement = $disbursementId
                ? CashDisbursement::query()->where('status', 'draft')->lockForUpdate()->findOrFail($disbursementId)
                : new CashDisbursement;
            if ($disbursement->reversal_of_id !== null) {
                throw ValidationException::withMessages(['disbursement' => 'A correction record cannot be edited.']);
            }
            $period = $this->periodFor($validated['paymentDate']);
            $disbursement->fill([
                'reference' => $validated['reference'],
                'payee' => $validated['payee'],
                'receipt_confirmed' => $validated['receiptConfirmed'],
                'supplier_id' => $validated['supplierId'],
                'payment_date' => $validated['paymentDate'],
                'method' => $validated['method'],
                'check_number' => $validated['checkNumber'] ?: null,
                'money_account_id' => $validated['moneyAccountId'],
                'description' => $validated['description'],
                'evidence_reference' => $validated['evidenceReference'],
                'amount_cents' => $validated['amountCents'],
                'status' => 'draft',
                'posting_period_id' => $period->id,
                'prepared_by' => $actorId,
            ])->save();
            $disbursement->lines()->delete();
            foreach ($validated['allocations'] as $line) {
                $disbursement->lines()->create($line);
            }

            return $disbursement->load(['lines.account', 'moneyAccount']);
        });
    }

    public function deleteDraft(int $disbursementId): void
    {
        DB::transaction(function () use ($disbursementId): void {
            $disbursement = CashDisbursement::query()->where('status', 'draft')
                ->whereNull('reversal_of_id')->lockForUpdate()->findOrFail($disbursementId);
            $disbursement->lines()->delete();
            $disbursement->delete();
        });
    }

    public function post(int $disbursementId, int $actorId): CashDisbursement
    {
        $actor = Employee::query()->findOrFail($actorId);
        if (! $actor->can('accounting.post-disbursements')) {
            abort(403);
        }

        return DB::transaction(function () use ($disbursementId, $actorId): CashDisbursement {
            $disbursement = CashDisbursement::query()->where('status', 'draft')->whereNull('reversal_of_id')
                ->lockForUpdate()->with(['lines.account', 'lines.inventory', 'moneyAccount'])->findOrFail($disbursementId);
            $this->assertPostingEligible($disbursement);
            $date = $disbursement->payment_date->toDateString();
            $period = $this->periodFor($date);
            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC',
                'reference' => 'CD-'.$disbursement->id,
                'source_type' => 'cash_disbursement',
                'source_id' => (string) $disbursement->id,
                'accounting_date' => $date,
                'posting_period_id' => $period->id,
                'description' => $disbursement->description.' — '.$disbursement->payee,
                'external_reference' => $disbursement->reference,
                'status' => 'draft',
                'prepared_by' => $disbursement->prepared_by,
            ]);
            $journalLines = $disbursement->lines->map(fn ($line) => [
                'accounting_account_id' => $line->accounting_account_id,
                'debit_cents' => $line->amount_cents,
                'credit_cents' => 0,
            ])->all();
            $journalLines[] = [
                'accounting_account_id' => $disbursement->money_account_id,
                'debit_cents' => 0,
                'credit_cents' => $disbursement->amount_cents,
            ];
            $journal->lines()->createMany($journalLines);
            foreach ($disbursement->lines->whereNotNull('inventory_id')->sortBy('id') as $line) {
                app(RecordValuedStockMovement::class)->handleDirectPurchaseReceipt(
                    (int) $line->inventory_id,
                    (string) $line->quantity,
                    $disbursement->reference,
                    $date,
                    $actorId,
                    (int) $line->amount_cents,
                    (int) $line->id,
                    $journal,
                );
            }
            $journal->forceFill(['status' => 'posted', 'posted_by' => $actorId, 'posted_at' => now('UTC')])->save();
            foreach ($disbursement->lines as $line) {
                $line->account->markUsed();
            }
            $disbursement->moneyAccount->markUsed();
            $disbursement->forceFill(['status' => 'posted', 'journal_id' => $journal->id, 'posted_by' => $actorId, 'posted_at' => now('UTC')])->save();

            return $disbursement->refresh()->load(['lines.account', 'moneyAccount', 'journal']);
        });
    }

    public function reverse(int $disbursementId, string $reason, int $actorId): CashDisbursement
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 4000) {
            throw ValidationException::withMessages(['reversalReason' => 'Provide a reason of at most 4,000 characters.']);
        }

        return DB::transaction(function () use ($disbursementId, $reason, $actorId): CashDisbursement {
            $original = CashDisbursement::query()->where('status', 'posted')->whereNull('reversal_of_id')
                ->lockForUpdate()->with(['lines.account', 'moneyAccount', 'journal.lines'])->findOrFail($disbursementId);
            if ($original->reversals()->exists()) {
                throw ValidationException::withMessages(['disbursement' => 'This payment already has a linked reversal.']);
            }
            if ($original->lines()->whereNotNull('inventory_id')->exists()) {
                throw ValidationException::withMessages(['disbursement' => 'Inventory receipts cannot be reversed through payment correction; use an authorized source-linked stock correction.']);
            }
            $this->assertPostingEligible($original, now('Asia/Manila')->toDateString(), true);
            $date = now('Asia/Manila')->toDateString();
            $period = $this->periodFor($date);
            $lines = $original->journal->lines->map(fn ($line) => [
                'accounting_account_id' => $line->accounting_account_id,
                'debit_cents' => (int) $line->credit_cents,
                'credit_cents' => (int) $line->debit_cents,
            ])->all();
            $reversal = CashDisbursement::query()->create([
                'reference' => 'REV-CD-'.$original->id,
                'payee' => $original->payee,
                'supplier_id' => $original->supplier_id,
                'payment_date' => $date,
                'method' => $original->method,
                'check_number' => $original->check_number,
                'money_account_id' => $original->money_account_id,
                'description' => 'Reversal of '.$original->reference.': '.$reason,
                'evidence_reference' => $original->evidence_reference,
                'amount_cents' => $original->amount_cents,
                'status' => 'draft',
                'posting_period_id' => $period->id,
                'reversal_of_id' => $original->id,
                'correction_reason' => $reason,
                'prepared_by' => $actorId,
            ]);
            $reversal->lines()->createMany($original->lines->map(fn ($line) => [
                'accounting_account_id' => $line->accounting_account_id,
                'supplier_purchase_invoice_id' => $line->supplier_purchase_invoice_id,
                'supplier_opening_invoice_id' => $line->supplier_opening_invoice_id,
                'description' => $line->description,
                'amount_cents' => $line->amount_cents,
            ])->all());
            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC',
                'reference' => 'CD-REV-'.$original->id,
                'source_type' => 'cash_disbursement_reversal',
                'source_id' => (string) $original->id,
                'accounting_date' => $date,
                'posting_period_id' => $period->id,
                'description' => $reversal->description,
                'external_reference' => $original->reference,
                'correction_of_id' => $original->journal_id,
                'correction_reason' => $reason,
                'status' => 'draft',
                'prepared_by' => $actorId,
            ]);
            $journal->lines()->createMany($lines);
            $journal->forceFill(['status' => 'posted', 'posted_by' => $actorId, 'posted_at' => now('UTC')])->save();
            $reversal->forceFill(['status' => 'posted', 'journal_id' => $journal->id, 'posted_by' => $actorId, 'posted_at' => now('UTC')])->save();

            return $reversal->load(['journal.lines.account', 'reversalOf']);
        });
    }

    private function validateDraft(array $input): array
    {
        $data = Validator::make($input, [
            'payee' => ['required', 'string', 'max:180'],
            'supplierId' => ['nullable', 'integer', 'exists:suppliers,id'],
            'paymentDate' => ['required', 'date_format:Y-m-d'],
            'method' => ['required', 'string', 'max:40'],
            'moneyAccountId' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'reference' => ['required', 'string', 'max:100'],
            'checkNumber' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:4000'],
            'evidenceReference' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string'],
            'receiptConfirmed' => ['sometimes', 'boolean'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.accounting_account_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
            'allocations.*.invoice_id' => ['nullable', 'string', 'max:120'],
            'allocations.*.inventory_id' => ['nullable', 'integer', 'exists:inventories,id'],
            'allocations.*.quantity' => ['nullable', 'string'],
            'allocations.*.description' => ['required', 'string', 'max:255'],
            'allocations.*.amount' => ['required', 'string'],
        ])->validate();

        if (! in_array($data['method'], ['Cash', 'Bank Transfer', 'Check'], true)) {
            $mapping = AccountingPostingMapping::query()->where('source', 'disbursement_method:'.$data['method'])->with('account')->first();
            if (! $mapping?->isApprovedForPosting() || $mapping->account->type !== 'Asset'
                || ! in_array($mapping->account->classification, ['cash', 'bank'], true)) {
                throw ValidationException::withMessages(['method' => 'Only explicitly approved additional payment methods are accepted.']);
            }
        }
        if ($data['method'] === 'Check' && trim((string) ($data['checkNumber'] ?? '')) === '') {
            throw ValidationException::withMessages(['checkNumber' => 'A check number is required for checks.']);
        }
        if ($data['method'] !== 'Cash' && trim($data['reference']) === '') {
            throw ValidationException::withMessages(['reference' => 'A payment reference is required for non-cash methods.']);
        }
        $amountCents = $this->cents($data['amount'], 'amount');
        $supplierId = isset($data['supplierId']) ? (int) $data['supplierId'] : null;
        $ap = $supplierId
            ? AccountingPostingMapping::query()->where('source', 'accounts_payable')->with('account')->first()?->account
            : null;
        if ($supplierId && (! $ap?->isApprovedForPosting() || $ap->classification !== 'accounts_payable' || $ap->type !== 'Liability')) {
            throw ValidationException::withMessages(['allocations' => 'An approved Accounts Payable mapping is required for supplier settlements.']);
        }
        $allocations = [];
        $sum = 0;
        $requestedByInvoice = [];
        foreach ($data['allocations'] as $index => $line) {
            $cents = $this->cents($line['amount'], "allocations.$index.amount");
            if ($cents <= 0 || $sum > PHP_INT_MAX - $cents) {
                throw ValidationException::withMessages(["allocations.$index.amount" => 'Each allocation must be positive and supported.']);
            }
            $sum += $cents;
            if ($supplierId) {
                [$invoiceType, $invoiceId] = $this->parseInvoiceReference($line['invoice_id'] ?? '');
                if ($invoiceType === null) {
                    throw ValidationException::withMessages(["allocations.$index.invoice_id" => 'Select a posted invoice belonging to this supplier.']);
                }
                $invoice = $invoiceType === 'purchase'
                    ? SupplierPurchaseInvoice::query()->whereKey($invoiceId)->where('supplier_id', $supplierId)
                        ->where('status', 'posted')->whereNull('correction_of_id')->first()
                    : SupplierOpeningInvoice::activePosted()->whereKey($invoiceId)->where('supplier_id', $supplierId)->first();
                if (! $invoice) {
                    throw ValidationException::withMessages(["allocations.$index.invoice_id" => 'Select a posted invoice belonging to this supplier.']);
                }
                $invoiceKey = $invoiceType.':'.$invoiceId;
                $requestedByInvoice[$invoiceKey] = ($requestedByInvoice[$invoiceKey] ?? 0) + $cents;
                if ($requestedByInvoice[$invoiceKey] > $invoice->outstandingAmountCents()) {
                    throw ValidationException::withMessages(["allocations.$index.amount" => 'An allocation cannot exceed the invoice current outstanding balance.']);
                }
                $allocations[] = [
                    'accounting_account_id' => $ap->id,
                    'supplier_purchase_invoice_id' => $invoiceType === 'purchase' ? $invoice->id : null,
                    'supplier_opening_invoice_id' => $invoiceType === 'opening' ? $invoice->id : null,
                    'description' => trim($line['description']),
                    'amount_cents' => $cents,
                ];
            } else {
                $inventoryId = isset($line['inventory_id']) && $line['inventory_id'] !== '' ? (int) $line['inventory_id'] : null;
                if ($inventoryId !== null) {
                    if (isset($line['accounting_account_id']) && $line['accounting_account_id'] !== '') {
                        throw ValidationException::withMessages(["allocations.$index" => 'An allocation cannot be both inventory and non-inventory.']);
                    }
                    $quantity = trim((string) ($line['quantity'] ?? ''));
                    if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $quantity) || (float) $quantity <= 0) {
                        throw ValidationException::withMessages(["allocations.$index.quantity" => 'Inventory purchases require a positive quantity with at most two decimal places.']);
                    }
                    $mapping = AccountingPostingMapping::query()->where('source', 'inventory')->with('account')->first();
                    if (! $mapping?->isApprovedForPosting() || $mapping->account->classification !== 'inventory') {
                        throw ValidationException::withMessages(['allocations' => 'An approved Inventory mapping is required for purchase allocations.']);
                    }
                    $allocations[] = [
                        'accounting_account_id' => $mapping->accounting_account_id,
                        'supplier_opening_invoice_id' => null,
                        'supplier_purchase_invoice_id' => null,
                        'inventory_id' => $inventoryId,
                        'quantity' => $quantity,
                        'stock_movement_id' => null,
                        'description' => trim($line['description']),
                        'amount_cents' => $cents,
                    ];
                } else {
                    if (empty($line['accounting_account_id'])) {
                        throw ValidationException::withMessages(["allocations.$index.accounting_account_id" => 'Select an approved debit account or inventory item.']);
                    }
                    $allocations[] = [
                        'accounting_account_id' => (int) $line['accounting_account_id'],
                        'supplier_opening_invoice_id' => null,
                        'supplier_purchase_invoice_id' => null,
                        'inventory_id' => null,
                        'quantity' => null,
                        'stock_movement_id' => null,
                        'description' => trim($line['description']),
                        'amount_cents' => $cents,
                    ];
                }
            }
        }
        if ($amountCents <= 0 || $sum !== $amountCents) {
            throw ValidationException::withMessages(['allocations' => 'Payment allocations must exactly equal the payment amount.']);
        }

        return [
            ...$data,
            'payee' => $supplierId ? Supplier::query()->findOrFail($supplierId)->name : trim($data['payee']),
            'reference' => trim($data['reference']),
            'description' => trim($data['description']),
            'evidenceReference' => trim($data['evidenceReference']),
            'moneyAccountId' => (int) $data['moneyAccountId'],
            'supplierId' => $supplierId,
            'amountCents' => $amountCents,
            'receiptConfirmed' => (bool) ($data['receiptConfirmed'] ?? false),
            'allocations' => $allocations,
        ];
    }

    private function assertPostingEligible(CashDisbursement $disbursement, ?string $postingDate = null, bool $isReversal = false): void
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $postingDate ?? $disbursement->payment_date->toDateString(), 'Asia/Manila');
        if ($date->greaterThan(CarbonImmutable::now('Asia/Manila')->startOfDay())) {
            throw ValidationException::withMessages(['paymentDate' => 'Completed payments cannot be future-dated.']);
        }
        $cutover = AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')
            ->where('status', 'posted')->value('accounting_date');
        $cutoverDate = $cutover instanceof \DateTimeInterface ? $cutover->format('Y-m-d') : substr((string) $cutover, 0, 10);
        if (! $cutover || $date->toDateString() < $cutoverDate) {
            throw ValidationException::withMessages(['paymentDate' => 'Payment date is outside approved accounting cutover coverage.']);
        }
        $period = $this->periodFor($date->toDateString());
        $period = AccountingPostingPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
        if ($period->status !== 'open' || $date->lt(CarbonImmutable::parse($period->starts_on, 'Asia/Manila'))
            || $date->gt(CarbonImmutable::parse($period->ends_on, 'Asia/Manila'))) {
            throw ValidationException::withMessages(['paymentDate' => 'Payment date is not in an open accounting period.']);
        }
        $moneyAccount = AccountingAccount::query()->lockForUpdate()->find($disbursement->money_account_id);
        if (! $moneyAccount?->isApprovedForPosting() || $moneyAccount->type !== 'Asset'
            || ! in_array($moneyAccount->classification, ['cash', 'bank'], true)) {
            throw ValidationException::withMessages(['moneyAccountId' => 'Select an active, approved Cash or Bank account.']);
        }
        $total = 0;
        if ($disbursement->lines->isEmpty()) {
            throw ValidationException::withMessages(['allocations' => 'At least one debit allocation is required.']);
        }
        $purchaseInvoiceIds = $disbursement->lines->pluck('supplier_purchase_invoice_id')->filter()->unique()->sort()->values();
        $openingInvoiceIds = $disbursement->lines->pluck('supplier_opening_invoice_id')->filter()->unique()->sort()->values();
        if ($disbursement->lines->contains(fn ($line) => $line->inventory_id !== null) && ! $disbursement->receipt_confirmed) {
            throw ValidationException::withMessages(['receiptConfirmed' => 'Confirm that all inventory items were physically received before posting the purchase payment.']);
        }
        if ($disbursement->supplier_id) {
            if ($disbursement->lines->contains(fn ($line) => $line->inventory_id !== null)) {
                throw ValidationException::withMessages(['allocations' => 'Supplier settlements cannot contain direct inventory purchase receipts.']);
            }
            if (($purchaseInvoiceIds->isEmpty() && $openingInvoiceIds->isEmpty())
                || $disbursement->lines->contains(fn ($line) => ($line->supplier_purchase_invoice_id === null) === ($line->supplier_opening_invoice_id === null))) {
                throw ValidationException::withMessages(['allocations' => 'Supplier payments require explicit invoice allocations only.']);
            }
            $purchaseInvoices = SupplierPurchaseInvoice::query()->whereIn('id', $purchaseInvoiceIds)
                ->whereNull('correction_of_id')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $openingInvoices = SupplierOpeningInvoice::activePosted()->whereIn('id', $openingInvoiceIds)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($purchaseInvoices->count() !== $purchaseInvoiceIds->count()
                || $openingInvoices->count() !== $openingInvoiceIds->count()) {
                throw ValidationException::withMessages(['allocations' => 'A selected invoice is no longer available for payment.']);
            }
            $ap = AccountingPostingMapping::query()->where('source', 'accounts_payable')->with('account')->lockForUpdate()->first()?->account;
            if (! $ap?->isApprovedForPosting() || $ap->classification !== 'accounts_payable' || $ap->type !== 'Liability') {
                throw ValidationException::withMessages(['allocations' => 'An approved Accounts Payable mapping is required.']);
            }
            $requested = [];
            foreach ($disbursement->lines as $line) {
                $isPurchase = $line->supplier_purchase_invoice_id !== null;
                $invoice = $isPurchase
                    ? $purchaseInvoices->get($line->supplier_purchase_invoice_id)
                    : $openingInvoices->get($line->supplier_opening_invoice_id);
                if (! $invoice || (int) $invoice->supplier_id !== (int) $disbursement->supplier_id
                    || (int) $line->accounting_account_id !== (int) $ap->id) {
                    throw ValidationException::withMessages(['allocations' => 'Payment allocations must reference posted invoices of this supplier and Accounts Payable.']);
                }
                $key = ($isPurchase ? 'purchase:' : 'opening:').$invoice->id;
                $requested[$key] = ($requested[$key] ?? 0) + (int) $line->amount_cents;
            }
            foreach ($requested as $key => $amount) {
                [$invoiceType, $invoiceId] = $this->parseInvoiceReference($key);
                $invoice = $invoiceType === 'purchase' ? $purchaseInvoices->get($invoiceId) : $openingInvoices->get($invoiceId);
                if (! $isReversal && $amount > $invoice->outstandingAmountCents()) {
                    throw ValidationException::withMessages(['allocations' => 'A payment cannot exceed an invoice current outstanding balance.']);
                }
            }
        } elseif ($disbursement->lines->contains(fn ($line) => $line->supplier_purchase_invoice_id !== null || $line->supplier_opening_invoice_id !== null)) {
            throw ValidationException::withMessages(['allocations' => 'Direct disbursements cannot contain supplier invoice allocations.']);
        }
        foreach ($disbursement->lines as $line) {
            $account = AccountingAccount::query()->lockForUpdate()->find($line->accounting_account_id);
            if ($disbursement->supplier_id) {
                if (! $account?->isApprovedForPosting() || (int) $account->id !== (int) $ap->id) {
                    throw ValidationException::withMessages(['allocations' => 'Supplier settlements debit only the approved Accounts Payable account.']);
                }
            } elseif ($line->inventory_id !== null) {
                $mapping = AccountingPostingMapping::query()->where('source', 'inventory')->with('account')->lockForUpdate()->first();
                $item = Inventory::query()->lockForUpdate()->find($line->inventory_id);
                if (! $item || $item->status !== 'active' || ! $mapping?->isApprovedForPosting()
                    || (int) $mapping->accounting_account_id !== (int) $line->accounting_account_id
                    || $mapping->account->classification !== 'inventory'
                    || trim((string) $line->quantity) === '' || (float) $line->quantity <= 0) {
                    throw ValidationException::withMessages(['allocations' => 'Inventory allocations require an active item, approved Inventory mapping, and positive quantity.']);
                }
            } elseif (! $account?->isApprovedForPosting() || ! in_array($account->type, ['Asset', 'Expense'], true)
                || in_array($account->classification, [
                    'cash', 'bank', 'accounts_receivable', 'card_clearing', 'accounts_payable',
                    'inventory', 'input_vat', 'output_vat', 'cost_of_goods_sold',
                ], true)) {
                throw ValidationException::withMessages(['allocations' => 'Debit allocations require active approved non-control Asset or Expense accounts.']);
            }
            if ($total > PHP_INT_MAX - (int) $line->amount_cents) {
                throw ValidationException::withMessages(['allocations' => 'Allocation total exceeds the supported PHP-centavo range.']);
            }
            $total += (int) $line->amount_cents;
        }
        if ($total !== (int) $disbursement->amount_cents) {
            throw ValidationException::withMessages(['allocations' => 'Debit allocations must exactly equal the payment amount.']);
        }
        if (! in_array($disbursement->method, ['Cash', 'Bank Transfer', 'Check'], true)) {
            $mapping = AccountingPostingMapping::query()->where('source', 'disbursement_method:'.$disbursement->method)
                ->with('account')->lockForUpdate()->first();
            if (! $mapping?->isApprovedForPosting() || $mapping->account->type !== 'Asset'
                || ! in_array($mapping->account->classification, ['cash', 'bank'], true)) {
                throw ValidationException::withMessages(['method' => 'Only explicitly approved additional payment methods are accepted.']);
            }
        }
    }

    private function parseInvoiceReference(mixed $reference): array
    {
        if (! preg_match('/^(purchase|opening):([1-9]\d*)$/D', trim((string) $reference), $matches)) {
            return [null, null];
        }
        $id = filter_var($matches[2], FILTER_VALIDATE_INT);

        return $id === false ? [null, null] : [$matches[1], $id];
    }

    private function periodFor(string $date): AccountingPostingPeriod
    {
        $year = (int) substr($date, 0, 4);

        return AccountingPostingPeriod::query()->firstOrCreate(
            ['book_key' => 'FCDC', 'fiscal_year' => $year],
            ['starts_on' => "$year-01-01", 'ends_on' => "$year-12-31", 'status' => 'open'],
        );
    }

    private function cents(mixed $amount, string $field): int
    {
        $value = trim((string) $amount);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([$field => 'Amounts must be non-negative PHP values with at most two decimal places.']);
        }
        [$pesos, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($pesos, '0') ?: '0';
        $maximumWhole = (string) intdiv(PHP_INT_MAX, 100);
        $fraction = str_pad($fraction, 2, '0');
        if (strlen($whole) > strlen($maximumWhole)
            || (strlen($whole) === strlen($maximumWhole) && strcmp($whole, $maximumWhole) > 0)
            || ($whole === $maximumWhole && (int) $fraction > PHP_INT_MAX % 100)) {
            throw ValidationException::withMessages([$field => 'Amount exceeds the supported PHP-centavo range.']);
        }

        return ((int) $whole * 100) + (int) $fraction;
    }
}
