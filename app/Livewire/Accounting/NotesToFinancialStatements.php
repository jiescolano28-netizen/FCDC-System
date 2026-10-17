<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingDisclosureNote;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Services\Accounting\OpeningBooksService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class NotesToFinancialStatements extends Component
{
    private const METRIC_DEFINITIONS = [
        'cash_and_bank' => ['label' => 'Cash and cash equivalents', 'classifications' => ['cash', 'bank', 'card_clearing'], 'side' => 'debit'],
        'accounts_receivable' => ['label' => 'Accounts receivable', 'classifications' => ['accounts_receivable'], 'side' => 'debit'],
        'inventory' => ['label' => 'Inventory', 'classifications' => ['inventory'], 'side' => 'debit'],
        'accounts_payable' => ['label' => 'Accounts payable', 'classifications' => ['accounts_payable'], 'side' => 'credit'],
        'revenue' => ['label' => 'Revenue', 'classifications' => ['sales', 'contra_revenue'], 'side' => 'credit'],
    ];

    public string $fromDate = '';

    public string $toDate = '';

    public string $noteReference = '';

    public string $title = '';

    public string $body = '';

    public string $disclosureType = AccountingDisclosureNote::TYPE_MANUAL;

    public ?int $editingNoteId = null;

    public bool $showEditor = false;

    public function mount(): void
    {
        $today = CarbonImmutable::now('Asia/Manila');
        $this->fromDate = $today->startOfMonth()->toDateString();
        $this->toDate = $today->toDateString();
    }

    public function saveNote(): void
    {
        $this->authorizePermission('accounting.manage-disclosures');
        validator([
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
            'noteReference' => $this->noteReference,
            'title' => $this->title,
            'body' => $this->body,
        ], [
            'fromDate' => ['required', 'date_format:Y-m-d'],
            'toDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:fromDate'],
            'noteReference' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $reference = trim($this->noteReference);
        if ($this->editingNoteId !== null) {
            $note = AccountingDisclosureNote::where('book_key', 'FCDC')
                ->where('status', AccountingDisclosureNote::STATUS_DRAFT)
                ->findOrFail($this->editingNoteId);
            $this->assertReferenceAvailable(
                $reference,
                $note->reporting_period_start->toDateString(),
                $note->reporting_period_end->toDateString(),
                $note->id,
            );
            $note->forceFill([
                'note_reference' => $reference,
                'title' => trim($this->title),
                'body' => trim($this->body) ?: null,
            ])->save();
        } else {
            $this->assertReferenceAvailable($reference, $this->fromDate, $this->toDate, null);
            AccountingDisclosureNote::create([
                'book_key' => 'FCDC',
                'reporting_period_start' => $this->fromDate,
                'reporting_period_end' => $this->toDate,
                'note_reference' => $reference,
                'title' => trim($this->title),
                'body' => trim($this->body) ?: null,
                'disclosure_type' => AccountingDisclosureNote::TYPE_MANUAL,
                'status' => AccountingDisclosureNote::STATUS_DRAFT,
                'prepared_by' => (int) auth()->id(),
            ]);
        }

        $this->resetEditor();
        $this->showEditor = false;
        session()->flash('disclosure-message', 'Disclosure note saved as a draft for review.');
    }

    public function openEditor(): void
    {
        $this->authorizePermission('accounting.manage-disclosures');
        $this->resetEditor();
        $this->showEditor = true;
    }

    public function closeEditor(): void
    {
        $this->showEditor = false;
    }

    public function addGeneratedNote(string $metric): void
    {
        $this->authorizePermission('accounting.manage-disclosures');
        validator(
            ['fromDate' => $this->fromDate, 'toDate' => $this->toDate],
            ['fromDate' => ['required', 'date_format:Y-m-d'], 'toDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:fromDate']],
        )->validate();
        $figures = $this->metricFigures();
        if (! $figures['available']) {
            throw ValidationException::withMessages(['generated' => $figures['error']]);
        }
        if (! isset($figures['metrics'][$metric])) {
            throw ValidationException::withMessages(['generated' => 'Choose a supported generated disclosure metric.']);
        }
        $row = $figures['metrics'][$metric];
        AccountingDisclosureNote::create([
            'book_key' => 'FCDC',
            'reporting_period_start' => $this->fromDate,
            'reporting_period_end' => $this->toDate,
            'note_reference' => $this->nextNoteReference($this->fromDate, $this->toDate),
            'title' => $row['label'],
            'body' => 'Auto-generated from the adjusted trial balance as of '.$this->toDate.'. Review the figure and add explanatory text before submitting.',
            'disclosure_type' => AccountingDisclosureNote::TYPE_GENERATED,
            'source_metric' => $metric,
            'generated_amount_cents' => $row['amount_cents'],
            'status' => AccountingDisclosureNote::STATUS_DRAFT,
            'prepared_by' => (int) auth()->id(),
        ]);

        session()->flash('disclosure-message', 'Generated disclosure note created for review.');
    }

    public function editNote(int $noteId): void
    {
        $this->authorizePermission('accounting.manage-disclosures');
        $note = AccountingDisclosureNote::where('book_key', 'FCDC')
            ->where('status', AccountingDisclosureNote::STATUS_DRAFT)->findOrFail($noteId);
        $this->editingNoteId = $note->id;
        $this->fromDate = $note->reporting_period_start->toDateString();
        $this->toDate = $note->reporting_period_end->toDateString();
        $this->noteReference = $note->note_reference;
        $this->title = $note->title;
        $this->body = $note->body ?? '';
        $this->disclosureType = $note->disclosure_type;
        $this->showEditor = true;
    }

    public function cancelEdit(): void
    {
        $this->resetEditor();
        $this->showEditor = false;
    }

    public function deleteNote(int $noteId): void
    {
        $this->authorizePermission('accounting.manage-disclosures');
        $note = AccountingDisclosureNote::where('book_key', 'FCDC')
            ->where('status', AccountingDisclosureNote::STATUS_DRAFT)->findOrFail($noteId);
        $note->delete();
        if ($this->editingNoteId === $noteId) {
            $this->resetEditor();
        }
        session()->flash('disclosure-message', 'Draft disclosure note deleted.');
    }

    public function submitNote(int $noteId): void
    {
        $this->authorizePermission('accounting.manage-disclosures');
        $note = AccountingDisclosureNote::where('book_key', 'FCDC')
            ->where('status', AccountingDisclosureNote::STATUS_DRAFT)->findOrFail($noteId);
        $note->forceFill(['status' => AccountingDisclosureNote::STATUS_PENDING_REVIEW])->save();
        if ($this->editingNoteId === $noteId) {
            $this->resetEditor();
        }
        session()->flash('disclosure-message', 'Disclosure note submitted for approval.');
    }

    public function returnNote(int $noteId): void
    {
        $this->authorizePermission('accounting.approve-disclosures');
        $note = AccountingDisclosureNote::where('book_key', 'FCDC')
            ->where('status', AccountingDisclosureNote::STATUS_PENDING_REVIEW)->findOrFail($noteId);
        $note->forceFill(['status' => AccountingDisclosureNote::STATUS_DRAFT])->save();
        session()->flash('disclosure-message', 'Disclosure note returned to draft.');
    }

    public function approveNote(int $noteId): void
    {
        $this->authorizePermission('accounting.approve-disclosures');
        $note = AccountingDisclosureNote::where('book_key', 'FCDC')
            ->where('status', AccountingDisclosureNote::STATUS_PENDING_REVIEW)->findOrFail($noteId);
        if ($note->prepared_by === (int) auth()->id()) {
            throw ValidationException::withMessages(['disclosure' => 'A different authorized employee must approve this disclosure note.']);
        }
        $note->forceFill([
            'status' => AccountingDisclosureNote::STATUS_APPROVED,
            'approved_at' => now('UTC'),
            'approved_by' => (int) auth()->id(),
        ])->save();
        session()->flash('disclosure-message', 'Disclosure note approved.');
    }

    public function finalizeNote(int $noteId): void
    {
        $this->authorizePermission('accounting.approve-disclosures');
        $note = AccountingDisclosureNote::where('book_key', 'FCDC')
            ->where('status', AccountingDisclosureNote::STATUS_APPROVED)->findOrFail($noteId);
        $note->forceFill([
            'status' => AccountingDisclosureNote::STATUS_FINALIZED,
            'finalized_at' => now('UTC'),
            'finalized_by' => (int) auth()->id(),
        ])->save();
        session()->flash('disclosure-message', 'Disclosure note finalized and protected.');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('accounting.view'), 403);

        $notes = AccountingDisclosureNote::query()
            ->where('book_key', 'FCDC')
            ->with(['preparer', 'approver', 'finalizer'])
            ->orderByDesc('reporting_period_end')
            ->orderBy('note_reference')
            ->get();

        return view('livewire.accounting.notes-to-financial-statements', [
            'figures' => $this->metricFigures(),
            'notes' => $notes,
            'metricLabels' => collect(self::METRIC_DEFINITIONS)->map(fn ($definition) => $definition['label'])->all(),
            'canManage' => auth()->user()?->can('accounting.manage-disclosures') ?? false,
            'canApprove' => auth()->user()?->can('accounting.approve-disclosures') ?? false,
            'generatedAt' => CarbonImmutable::now('Asia/Manila')->format('F j, Y g:i A').' Asia/Manila',
        ])->layout('layouts.app', ['title' => 'Notes to Financial Statements / Disclosure']);
    }

    /**
     * @return array{available: bool, error: ?string, asOf: string, metrics: array<string, array{label: string, amount_cents: int}>}
     */
    private function metricFigures(): array
    {
        $empty = ['available' => false, 'error' => null, 'asOf' => $this->toDate, 'metrics' => []];
        $readiness = app(OpeningBooksService::class)->readiness();
        if (! $readiness['production_activated']) {
            return [...$empty, 'error' => $readiness['production']];
        }
        if (! $this->validDate($this->fromDate) || ! $this->validDate($this->toDate) || $this->fromDate > $this->toDate) {
            return [...$empty, 'error' => 'Choose a valid inclusive reporting period.'];
        }
        $cutover = AccountingJournal::query()
            ->where('book_key', 'FCDC')->where('source_type', 'opening')->where('source_id', 'FCDC')
            ->where('status', 'posted')->value('accounting_date');
        if (! $cutover) {
            return [...$empty, 'error' => 'Posted accounting cutover coverage is unavailable until approved opening balances are posted.'];
        }
        $cutoverDate = CarbonImmutable::parse($cutover, 'Asia/Manila')->toDateString();

        $byClassification = AccountingJournalLine::query()
            ->join('accounting_journals', 'accounting_journals.id', '=', 'accounting_journal_lines.accounting_journal_id')
            ->join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_journal_lines.accounting_account_id')
            ->where('accounting_journals.book_key', 'FCDC')
            ->where('accounting_journals.status', 'posted')
            ->whereDate('accounting_journals.accounting_date', '>=', $cutoverDate)
            ->whereDate('accounting_journals.accounting_date', '<=', $this->toDate)
            ->toBase()
            ->selectRaw('accounting_accounts.classification as classification')
            ->selectRaw('SUM(accounting_journal_lines.debit_cents) as debit_cents')
            ->selectRaw('SUM(accounting_journal_lines.credit_cents) as credit_cents')
            ->groupBy('accounting_accounts.classification')
            ->get()
            ->keyBy('classification');

        $metrics = [];
        foreach (self::METRIC_DEFINITIONS as $key => $definition) {
            $debit = 0;
            $credit = 0;
            foreach ($definition['classifications'] as $classification) {
                $row = $byClassification->get($classification);
                $debit += (int) ($row->debit_cents ?? 0);
                $credit += (int) ($row->credit_cents ?? 0);
            }
            $metrics[$key] = [
                'label' => $definition['label'],
                'amount_cents' => $definition['side'] === 'debit' ? $debit - $credit : $credit - $debit,
            ];
        }

        return ['available' => true, 'error' => null, 'asOf' => $this->toDate, 'metrics' => $metrics];
    }

    private function nextNoteReference(string $start, string $end): string
    {
        $existing = AccountingDisclosureNote::where('book_key', 'FCDC')
            ->whereDate('reporting_period_start', $start)
            ->whereDate('reporting_period_end', $end)
            ->pluck('note_reference');
        $number = $existing->count() + 1;
        do {
            $reference = 'Note '.$number;
            $number++;
        } while ($existing->contains($reference));

        return $reference;
    }

    private function assertReferenceAvailable(string $reference, string $start, string $end, ?int $ignoreId): void
    {
        $exists = AccountingDisclosureNote::where('book_key', 'FCDC')
            ->whereDate('reporting_period_start', $start)
            ->whereDate('reporting_period_end', $end)
            ->where('note_reference', $reference)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['noteReference' => 'This note reference is already used for the reporting period.']);
        }
    }

    private function resetEditor(): void
    {
        $this->reset('noteReference', 'title', 'body', 'editingNoteId');
        $this->disclosureType = AccountingDisclosureNote::TYPE_MANUAL;
    }

    private function validDate(string $date): bool
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
