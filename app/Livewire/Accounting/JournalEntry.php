<?php

namespace App\Livewire\Accounting;

use App\Support\ReferenceAccounts;
use Illuminate\Validation\Rule;
use Livewire\Component;

class JournalEntry extends Component
{
    public string $date = '';

    public string $reference = '';

    public string $source = '';

    public string $description = '';

    public array $lines = [];

    public function mount(): void
    {
        $draft = session()->get($this->draftKey());

        if ($draft) {
            $this->fill($draft);

            return;
        }

        $this->date = now()->toDateString();
        $this->lines = [$this->emptyLine(), $this->emptyLine()];
    }

    public function updated(): void
    {
        session()->put($this->draftKey(), $this->draft());
    }

    public function addLine(): void
    {
        if (count($this->lines) < 10) {
            $this->lines[] = $this->emptyLine();
            session()->put($this->draftKey(), $this->draft());
        }
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 2 || ! array_key_exists($index, $this->lines)) {
            return;
        }

        array_splice($this->lines, $index, 1);
        session()->put($this->draftKey(), $this->draft());
    }

    public function clear(): void
    {
        session()->forget($this->draftKey());
        $this->resetValidation();
        $this->date = now()->toDateString();
        $this->reference = '';
        $this->source = '';
        $this->description = '';
        $this->lines = [$this->emptyLine(), $this->emptyLine()];
    }

    public function save(): void
    {
        $codes = collect(ReferenceAccounts::all())->pluck('code')->all();
        $this->validate([
            'date' => ['required', 'date'],
            'reference' => ['required', 'string', 'max:100'],
            'source' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2', 'max:10'],
            'lines.*.accountCode' => ['required', Rule::in($codes)],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $debitCents = 0;
        $creditCents = 0;

        foreach ($this->lines as $index => $line) {
            $debit = $this->amountInCents($line['debit'] ?? null);
            $credit = $this->amountInCents($line['credit'] ?? null);

            if ($debit > 0 && $credit > 0) {
                $this->addError("lines.{$index}", 'A line cannot contain both a debit and a credit.');

                return;
            }

            $debitCents += $debit;
            $creditCents += $credit;
        }

        if ($debitCents <= 0 || $creditCents <= 0 || $debitCents !== $creditCents) {
            $this->addError('lines', 'The demonstration entry must have equal, positive debit and credit totals.');

            return;
        }

        $history = session()->get($this->historyKey(), []);
        array_unshift($history, [
            'date' => $this->date,
            'reference' => $this->reference,
            'source' => $this->source,
            'description' => $this->description,
            'lines' => $this->lines,
            'debitTotal' => $debitCents / 100,
            'creditTotal' => $creditCents / 100,
            'demo' => true,
            'posted' => false,
        ]);

        session()->put($this->historyKey(), $history);
        $this->clear();
        session()->flash('journal-entry-saved', 'Demonstration entry saved. Not posted.');
    }

    public function render()
    {
        $debitTotal = collect($this->lines)->sum(fn (array $line): int => $this->amountInCents($line['debit'] ?? null)) / 100;
        $creditTotal = collect($this->lines)->sum(fn (array $line): int => $this->amountInCents($line['credit'] ?? null)) / 100;

        return view('livewire.accounting.journal-entry', [
            'accounts' => ReferenceAccounts::all(),
            'history' => session()->get($this->historyKey(), []),
            'debitTotal' => $debitTotal,
            'creditTotal' => $creditTotal,
        ])->layout('layouts.app', ['title' => 'Journal Entry']);
    }

    private function emptyLine(): array
    {
        return ['accountCode' => '', 'description' => '', 'debit' => '', 'credit' => ''];
    }

    private function draft(): array
    {
        return [
            'date' => $this->date,
            'reference' => $this->reference,
            'source' => $this->source,
            'description' => $this->description,
            'lines' => $this->lines,
        ];
    }

    private function amountInCents(mixed $amount): int
    {
        return (int) round((float) ($amount ?: 0) * 100);
    }

    private function draftKey(): string
    {
        return 'demo.journals.employee.'.auth()->id().'.draft';
    }

    private function historyKey(): string
    {
        return 'demo.journals.employee.'.auth()->id().'.history';
    }
}
