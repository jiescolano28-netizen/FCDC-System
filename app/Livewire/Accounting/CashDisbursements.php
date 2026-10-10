<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\CashDisbursement as CashDisbursementRecord;
use App\Services\Accounting\CashDisbursementService;
use Livewire\Attributes\Url;
use Livewire\Component;

class CashDisbursements extends Component
{
    public ?int $editingId = null;

    #[Url(as: 'disbursement')]
    public ?int $selectedId = null;

    public string $payee = '';

    public string $paymentDate = '';

    public string $method = 'Cash';

    public string $moneyAccountId = '';

    public string $reference = '';

    public string $checkNumber = '';

    public string $description = '';

    public string $evidenceReference = '';

    public string $amount = '';

    public array $allocations = [];

    public string $methodFilter = 'All';
    public string $search = '';

    public string $status = 'All';

    public string $fromDate = '';

    public string $toDate = '';

    public string $reversalReason = '';

    public function mount(): void
    {
        $this->paymentDate = now('Asia/Manila')->toDateString();
        $this->allocations = [$this->emptyAllocation()];
    }

    public function addAllocation(): void
    {
        $this->authorizePermission('accounting.prepare-disbursements');
        $this->allocations[] = $this->emptyAllocation();
    }

    public function removeAllocation(int $index): void
    {
        $this->authorizePermission('accounting.prepare-disbursements');
        if (count($this->allocations) > 1 && array_key_exists($index, $this->allocations)) {
            array_splice($this->allocations, $index, 1);
        }
    }

    public function saveDraft(): void
    {
        $this->authorizePermission('accounting.prepare-disbursements');
        $disbursement = app(CashDisbursementService::class)->saveDraft([
            'payee' => $this->payee,
            'paymentDate' => $this->paymentDate,
            'method' => $this->method,
            'moneyAccountId' => $this->moneyAccountId,
            'reference' => $this->reference,
            'checkNumber' => $this->checkNumber,
            'description' => $this->description,
            'evidenceReference' => $this->evidenceReference,
            'amount' => $this->amount,
            'allocations' => $this->allocations,
        ], (int) auth()->id(), $this->editingId);
        $this->selectedId = $disbursement->id;
        $this->resetForm();
        session()->flash('disbursement-message', 'Direct disbursement draft saved; it has no cash or journal effect.');
    }

    public function editDraft(int $disbursementId): void
    {
        $this->authorizePermission('accounting.prepare-disbursements');
        $record = CashDisbursementRecord::query()->where('status', 'draft')->whereNull('reversal_of_id')
            ->with('lines')->findOrFail($disbursementId);
        $this->editingId = $record->id;
        $this->payee = $record->payee;
        $this->paymentDate = $record->payment_date->toDateString();
        $this->method = $record->method;
        $this->moneyAccountId = (string) $record->money_account_id;
        $this->reference = $record->reference;
        $this->checkNumber = $record->check_number ?? '';
        $this->description = $record->description;
        $this->evidenceReference = $record->evidence_reference;
        $this->amount = number_format($record->amount_cents / 100, 2, '.', '');
        $this->allocations = $record->lines->map(fn ($line) => [
            'accounting_account_id' => (string) $line->accounting_account_id,
            'description' => $line->description,
            'amount' => number_format($line->amount_cents / 100, 2, '.', ''),
        ])->all();
    }

    public function deleteDraft(int $disbursementId): void
    {
        $this->authorizePermission('accounting.prepare-disbursements');
        app(CashDisbursementService::class)->deleteDraft($disbursementId);
        if ($this->editingId === $disbursementId) {
            $this->resetForm();
        }
        session()->flash('disbursement-message', 'Direct disbursement draft deleted.');
    }

    public function postDisbursement(int $disbursementId): void
    {
        $this->authorizePermission('accounting.post-disbursements');
        app(CashDisbursementService::class)->post($disbursementId, (int) auth()->id());
        session()->flash('disbursement-message', 'Direct payment and balanced cash/bank journal posted atomically.');
    }

    public function reverseDisbursement(int $disbursementId): void
    {
        $this->authorizePermission('accounting.post-disbursements');
        app(CashDisbursementService::class)->reverse($disbursementId, $this->reversalReason, (int) auth()->id());
        $this->reversalReason = '';
        session()->flash('disbursement-message', 'Linked disbursement reversal posted; original payment history is unchanged.');
    }

    public function showDisbursement(int $disbursementId): void
    {
        $this->authorizePermission('accounting.view');
        $this->selectedId = CashDisbursementRecord::query()->findOrFail($disbursementId)->id;
    }

    public function render()
    {
        $this->authorizePermission('accounting.view');
        $query = CashDisbursementRecord::query()->with(['lines.account', 'moneyAccount', 'journal', 'reversalOf', 'reversals'])
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('reference', 'like', $term)->orWhere('payee', 'like', $term)
                        ->orWhere('description', 'like', $term)->orWhere('evidence_reference', 'like', $term);
                });
            })
            ->when(in_array($this->status, ['Draft', 'Posted'], true), fn ($query) => $query->where('status', strtolower($this->status)))
            ->when($this->fromDate !== '', fn ($query) => $query->whereDate('payment_date', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn ($query) => $query->whereDate('payment_date', '<=', $this->toDate))
            ->when($this->methodFilter !== 'All', fn ($query) => $query->where('method', $this->methodFilter))
            ->orderByDesc('payment_date')->orderByDesc('id');
        $disbursements = $query->get();
        $selected = $this->selectedId
            ? CashDisbursementRecord::query()->with(['lines.account', 'moneyAccount', 'journal.lines.account', 'reversalOf', 'reversals.journal'])->find($this->selectedId)
            : null;
        $otherMethods = AccountingPostingMapping::query()->where('source', 'like', 'disbursement_method:%')
            ->with('account')->get()->filter(fn ($mapping) => $mapping->isApprovedForPosting())
            ->map(fn ($mapping) => substr($mapping->source, strlen('disbursement_method:')))->values();

        return view('livewire.accounting.cash-disbursements', [
            'disbursements' => $disbursements,
            'selectedDisbursement' => $selected,
            'moneyAccounts' => AccountingAccount::query()->where('is_active', true)->whereNotNull('approved_at')
                ->where('type', 'Asset')->whereIn('classification', ['cash', 'bank'])->orderBy('code')->get(),
            'debitAccounts' => AccountingAccount::query()->where('is_active', true)->whereNotNull('approved_at')
                ->whereIn('type', ['Asset', 'Expense'])
                ->whereNotIn('classification', ['cash', 'bank', 'accounts_receivable', 'card_clearing', 'accounts_payable', 'inventory', 'input_vat', 'output_vat', 'cost_of_goods_sold'])
                ->orderBy('code')->get(),
            'otherMethods' => $otherMethods,
            'postedTotalCents' => $disbursements->where('status', 'posted')->whereNull('reversal_of_id')->sum('amount_cents'),
        ])->layout('layouts.app', ['title' => 'Cash Disbursements']);
    }

    private function emptyAllocation(): array
    {
        return ['accounting_account_id' => '', 'description' => '', 'amount' => ''];
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'payee', 'method', 'moneyAccountId', 'reference', 'checkNumber', 'description', 'evidenceReference', 'amount']);
        $this->paymentDate = now('Asia/Manila')->toDateString();
        $this->method = 'Cash';
        $this->allocations = [$this->emptyAllocation()];
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
