<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Services\Accounting\JournalSourcePostingService;
use App\Services\Accounting\ManualJournalService;
use App\Services\Accounting\OpeningBooksService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

class JournalEntry extends Component
{
    public string $accountingDate = '';

    public string $cutoverDate = '';

    public array $openingLines = [];

    public string $reference = '';

    public string $externalReference = '';

    public string $description = '';

    public string $entryType = 'standard';

    public array $lines = [];

    public ?int $editingDraftId = null;

    public bool $showEntryModal = false;

    #[Url(as: 'journal')]
    public ?int $selectedJournalId = null;

    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $sourceFilter = '';

    public string $statusFilter = '';

    public string $entryTypeFilter = '';

    public string $accountTypeFilter = '';

    public string $correctionReason = '';

    public string $correctionReference = '';

    public string $replacementReference = '';

    public ?int $correctingJournalId = null;

    public bool $openingAttempted = false;

    public function mount(): void
    {
        $this->accountingDate = now('Asia/Manila')->toDateString();
        $this->cutoverDate = now('Asia/Manila')->toDateString();
        $this->lines = [$this->emptyLine(), $this->emptyLine()];
        $this->openingLines = [$this->emptyOpeningLine(), $this->emptyOpeningLine()];

        $openingJournal = AccountingJournal::query()
            ->where('source_type', 'opening')
            ->where('source_id', 'FCDC')
            ->with('lines')
            ->first();
        if ($openingJournal) {
            $this->cutoverDate = $openingJournal->accounting_date->format('Y-m-d');
            if ($openingJournal->status === 'draft') {
                $this->openingLines = $openingJournal->lines->map(fn ($line) => [
                    'accountId' => (string) $line->accounting_account_id,
                    'debit' => $line->debit_cents ? number_format($line->debit_cents / 100, 2, '.', '') : '',
                    'credit' => $line->credit_cents ? number_format($line->credit_cents / 100, 2, '.', '') : '',
                ])->all();
            }
        }
    }

    public function addOpeningLine(): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        if (count($this->openingLines) < 100) {
            $this->openingLines[] = $this->emptyOpeningLine();
        }
    }

    public function removeOpeningLine(int $index): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        if (count($this->openingLines) > 2 && array_key_exists($index, $this->openingLines)) {
            array_splice($this->openingLines, $index, 1);
        }
    }

    public function saveOpening(): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        $this->openingAttempted = true;
        app(OpeningBooksService::class)->saveOpening($this->cutoverDate, $this->openingLines, (int) auth()->id());
        $this->openingAttempted = false;
        session()->flash('journal-entry-message', 'Opening balance journal saved. Review the balanced cutover lines, then approve them.');
    }

    public function approveOpening(): void
    {
        $this->authorizePermission('accounting.approve-opening-books');
        $this->openingAttempted = true;
        app(OpeningBooksService::class)->approveOpening((int) auth()->id());
        $this->openingAttempted = false;
        session()->flash('journal-entry-message', 'Opening balance journal approved and posted. The General Ledger now uses these balances.');
    }

    public function addLine(): void
    {
        $this->authorizePermission('accounting.create-journal-entry');
        if (count($this->lines) < 100) {
            $this->lines[] = $this->emptyLine();
        }
    }

    public function openNewDraft(): void
    {
        $this->authorizePermission('accounting.create-journal-entry');
        $this->openingAttempted = false;
        $this->resetForm();
        $this->showEntryModal = true;
    }

    public function closeEntryModal(): void
    {
        $this->showEntryModal = false;
    }

    public function removeLine(int $index): void
    {
        $this->authorizePermission('accounting.create-journal-entry');
        if (count($this->lines) > 2 && array_key_exists($index, $this->lines)) {
            array_splice($this->lines, $index, 1);
        }
    }

    public function saveDraft(): void
    {
        $this->authorizePermission('accounting.create-journal-entry');
        $this->openingAttempted = false;
        $journal = app(ManualJournalService::class)->saveDraft($this->formData(), auth()->id(), $this->editingDraftId);
        $this->editingDraftId = $journal->id;
        $this->showEntryModal = true;
        session()->flash('journal-entry-message', 'Journal draft saved. Review the lines and submit it when ready.');
    }

    public function submitForReview(int $journalId): void
    {
        $this->authorizePermission('accounting.create-journal-entry');
        app(ManualJournalService::class)->submitForReview($journalId, (int) auth()->id());
        if ($this->editingDraftId === $journalId) {
            $this->resetForm();
            $this->showEntryModal = false;
        }
        session()->flash('journal-entry-message', 'Journal submitted for approval.');
    }

    public function returnForChanges(int $journalId): void
    {
        $this->authorizePermission('accounting.approve-journal-entry');
        app(ManualJournalService::class)->returnForChanges($journalId, (int) auth()->id());
        session()->flash('journal-entry-message', 'Journal returned to draft so the preparer can make changes.');
    }

    public function editDraft(int $journalId): void
    {
        $this->authorizePermission('accounting.create-journal-entry');
        $this->openingAttempted = false;
        $journal = AccountingJournal::where('source_type', 'manual')->where('status', 'draft')
            ->with('lines')->findOrFail($journalId);
        $this->editingDraftId = $journal->id;
        $this->fillFromJournal($journal);
        $this->showEntryModal = true;
    }

    public function deleteDraft(int $journalId): void
    {
        $this->authorizePermission('accounting.create-journal-entry');
        app(ManualJournalService::class)->deleteDraft($journalId);
        if ($this->editingDraftId === $journalId) {
            $this->resetForm();
        }
        session()->flash('journal-entry-message', 'Journal draft deleted.');
    }

    public function postDraft(int $journalId): void
    {
        $this->authorizePermission('accounting.post-journal-entry');
        app(ManualJournalService::class)->postDraft($journalId, auth()->id());
        session()->flash('journal-entry-message', 'Journal posted to the accounting books.');
    }

    public function approveDraft(int $journalId): void
    {
        $this->authorizePermission('accounting.approve-journal-entry');
        app(ManualJournalService::class)->approveDraft($journalId, (int) auth()->id());
        session()->flash('journal-entry-message', 'Journal approved and ready to post.');
    }

    public function showJournal(int $journalId): void
    {
        $this->selectedJournalId = AccountingJournal::findOrFail($journalId)->id;
    }

    public function generateSourceJournal(string $sourceType, int $sourceId): void
    {
        $this->authorizePermission('accounting.post-journal-entry');
        $journal = app(JournalSourcePostingService::class)->generate($sourceType, $sourceId, (int) auth()->id());
        $this->selectedJournalId = $journal->id;
        session()->flash(
            'journal-entry-message',
            'Source journal '.$journal->reference.' is posted to the accounting books.',
        );
    }

    public function sourceLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'pos_sale' => 'POS sale',
            'reworking_cost' => 'Reworking cost',
            'operating_cost' => 'Operating cost',
            'manual' => 'Manual input',
            'reversal' => 'Correction reversal',
            'replacement' => 'Correction replacement',
            'opening' => 'Opening balances',
            default => ucwords(str_replace('_', ' ', $sourceType)),
        };
    }

    public function beginCorrection(int $journalId): void
    {
        $this->authorizePermission('accounting.create-journal-entry');
        $this->openingAttempted = false;
        $journal = AccountingJournal::where('status', 'posted')
            ->whereIn('source_type', ['manual', 'replacement'])
            ->whereDoesntHave('corrections')->with('lines')->findOrFail($journalId);
        $this->fillFromJournal($journal);
        $this->correctingJournalId = $journal->id;
        $this->accountingDate = now('Asia/Manila')->toDateString();
        $this->reference = '';
        $this->replacementReference = '';
        $this->correctionReference = '';
        $this->correctionReason = '';
        $this->showEntryModal = true;
    }

    public function correct(int $journalId): void
    {
        $this->authorizePermission('accounting.approve-journal-entry');
        $this->authorizePermission('accounting.post-journal-entry');
        try {
            app(ManualJournalService::class)->correct($journalId, array_merge($this->formData(), [
                'reference' => $this->replacementReference,
                'correctionReason' => $this->correctionReason,
                'correctionReference' => $this->correctionReference,
            ]), auth()->id());
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            if (isset($errors['reference'])) {
                $errors['replacementReference'] = $errors['reference'];
                unset($errors['reference']);
            }
            throw ValidationException::withMessages($errors);
        }
        $this->correctingJournalId = null;
        $this->resetForm();
        $this->showEntryModal = false;
        session()->flash('journal-entry-message', 'Linked reversal and replacement posted.');
    }

    public function render()
    {
        $journals = AccountingJournal::query()->with(['lines.account', 'preparer', 'approver', 'poster', 'correctionOf', 'corrections'])
            ->when($this->search !== '', function ($query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn ($nested) => $nested->where('reference', 'like', $term)
                    ->orWhere('external_reference', 'like', $term)
                    ->orWhere('description', 'like', $term));
            })
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('accounting_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('accounting_date', '<=', $this->dateTo))
            ->when($this->sourceFilter !== '', fn ($query) => $query->where('source_type', $this->sourceFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->entryTypeFilter !== '', fn ($query) => $query->where('entry_type', $this->entryTypeFilter))
            ->when($this->accountTypeFilter !== '', fn ($query) => $this->applyAccountTypeFilter($query))
            ->orderByDesc('accounting_date')->orderByDesc('id')->get();

        [$debitTotal, $creditTotal, $isBalanced] = $this->previewTotals();
        [$openingDebitTotal, $openingCreditTotal, $isOpeningBalanced] = $this->openingTotals();

        $accounts = AccountingAccount::where('is_active', true)->whereNotNull('approved_at')->orderBy('code')->get();
        $openingJournal = AccountingJournal::query()
            ->where('source_type', 'opening')
            ->where('source_id', 'FCDC')
            ->with(['lines.account', 'preparer', 'approver'])
            ->first();

        return view('livewire.accounting.journal-entry', [
            'journals' => $journals,
            'selectedJournal' => $this->selectedJournalId ? AccountingJournal::with(['lines.account', 'poster', 'preparer', 'approver', 'correctionOf', 'corrections'])->find($this->selectedJournalId) : null,
            'accounts' => $accounts,
            'accountsByType' => $accounts->groupBy(fn (AccountingAccount $account): string => $account->displayType()),
            'isCorrecting' => $this->correctingJournalId !== null,
            'debitTotal' => $debitTotal,
            'creditTotal' => $creditTotal,
            'isBalanced' => $isBalanced,
            'openingJournal' => $openingJournal,
            'openingDebitTotal' => $openingDebitTotal,
            'openingCreditTotal' => $openingCreditTotal,
            'isOpeningBalanced' => $isOpeningBalanced,
            'sourceCandidates' => app(JournalSourcePostingService::class)->pendingCandidates(),
            'sourceLabels' => $this->sourceLabels(),
        ])->layout('layouts.app', ['title' => 'Journal Entry']);
    }

    /**
     * @return array<string, string>
     */
    private function sourceLabels(): array
    {
        return [
            'manual' => 'Manual input',
            'pos_sale' => 'POS sale',
            'reworking_cost' => 'Reworking cost',
            'operating_cost' => 'Operating cost',
            'reversal' => 'Correction reversal',
            'replacement' => 'Correction replacement',
            'opening' => 'Opening balances',
        ];
    }

    private function applyAccountTypeFilter(Builder $query): void
    {
        $query->whereHas('lines.account', function (Builder $accounts): void {
            match ($this->accountTypeFilter) {
                'Asset', 'Liability', 'Equity', 'Revenue' => $accounts->where('type', $this->accountTypeFilter),
                'contra_revenue' => $accounts->where('classification', 'contra_revenue'),
                'cost_of_sales' => $accounts->where('classification', 'cost_of_goods_sold'),
                'operating_expense' => $accounts->where('classification', 'operating_expense'),
                default => $accounts->whereRaw('1 = 0'),
            };
        });
    }

    private function formData(): array
    {
        return [
            'accountingDate' => $this->accountingDate,
            'reference' => $this->reference,
            'externalReference' => $this->externalReference,
            'description' => $this->description,
            'entryType' => $this->entryType,
            'lines' => $this->lines,
        ];
    }

    private function fillFromJournal(AccountingJournal $journal): void
    {
        $this->accountingDate = $journal->accounting_date->format('Y-m-d');
        $this->reference = $journal->reference;
        $this->externalReference = $journal->external_reference ?? '';
        $this->description = $journal->description;
        $this->entryType = $journal->entry_type ?? 'standard';
        $this->lines = $journal->lines->map(fn ($line) => [
            'accountId' => (string) $line->accounting_account_id,
            'description' => $line->description ?? '',
            'debit' => $line->debit_cents ? number_format($line->debit_cents / 100, 2, '.', '') : '',
            'credit' => $line->credit_cents ? number_format($line->credit_cents / 100, 2, '.', '') : '',
        ])->all();
    }

    private function previewTotals(): array
    {
        return $this->totalsFor($this->lines);
    }

    private function openingTotals(): array
    {
        return $this->totalsFor($this->openingLines);
    }

    private function totalsFor(array $lines): array
    {
        $totals = ['debit' => 0.0, 'credit' => 0.0];
        $valid = true;

        foreach ($lines as $line) {
            foreach (array_keys($totals) as $side) {
                $amount = trim((string) ($line[$side] ?? ''));
                if ($amount === '') {
                    continue;
                }
                if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount)) {
                    $valid = false;

                    continue;
                }

                $totals[$side] += (float) $amount;
                if (! is_finite($totals[$side])) {
                    $valid = false;
                }
            }
        }

        $debitTotal = round($totals['debit'], 2);
        $creditTotal = round($totals['credit'], 2);

        return [$debitTotal, $creditTotal, $valid && $debitTotal > 0 && $debitTotal === $creditTotal];
    }

    private function resetForm(): void
    {
        $this->reset('reference', 'externalReference', 'description', 'editingDraftId', 'correctingJournalId');
        $this->accountingDate = now('Asia/Manila')->toDateString();
        $this->entryType = 'standard';
        $this->lines = [$this->emptyLine(), $this->emptyLine()];
    }

    private function emptyLine(): array
    {
        return ['accountId' => '', 'description' => '', 'debit' => '', 'credit' => ''];
    }

    private function emptyOpeningLine(): array
    {
        return ['accountId' => '', 'debit' => '', 'credit' => ''];
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
