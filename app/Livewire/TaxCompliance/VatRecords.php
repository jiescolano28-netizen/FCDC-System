<?php

namespace App\Livewire\TaxCompliance;

use App\Models\PosTransaction;
use App\Models\PosVatRecord;
use App\Services\RecordedInputVat;
use App\Services\RecordedVatSummary;
use App\Support\TaxClassification;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class VatRecords extends Component
{
    use WithPagination;

    public string $search = '';

    public string $transactionNumber = '';

    public string $taxClassification = '';

    public string $transactionStatus = '';

    public string $startDate = '';

    public string $endDate = '';

    public ?int $selectedRecordId = null;

    public function mount(): void
    {
        $this->startDate = now('Asia/Manila')->startOfMonth()->toDateString();
        $this->endDate = now('Asia/Manila')->endOfMonth()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTransactionNumber(): void
    {
        $this->resetPage();
    }

    public function updatedTaxClassification(): void
    {
        $this->resetPage();
    }

    public function updatedTransactionStatus(): void
    {
        $this->resetPage();
    }

    public function updatedStartDate(): void
    {
        $this->resetPage();
    }

    public function updatedEndDate(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->transactionNumber = '';
        $this->taxClassification = '';
        $this->transactionStatus = '';
        $this->mount();
        $this->resetPage();
    }

    public function viewRecord(int $recordId): void
    {
        $this->authorizePermission();

        $this->selectedRecordId = PosVatRecord::query()->whereKey($recordId)->value('id');
    }

    public function closeDetails(): void
    {
        $this->authorizePermission();
        $this->selectedRecordId = null;
    }

    public function render()
    {
        $this->authorizePermission();
        $search = mb_strtolower(trim($this->search));
        $transactionNumber = mb_strtolower(trim($this->transactionNumber));
        $records = PosVatRecord::query()
            ->with('posTransaction.lines')
            ->when($search !== '', fn ($query) => $query->whereHas(
                'posTransaction',
                fn ($transaction) => $transaction->where(
                    fn ($transactionQuery) => $transactionQuery
                        ->whereRaw('LOWER(transaction_number) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(customer_name) LIKE ?', ['%'.$search.'%']),
                ),
            ))
            ->when($transactionNumber !== '', fn ($query) => $query->whereHas(
                'posTransaction',
                fn ($transaction) => $transaction->whereRaw('LOWER(transaction_number) LIKE ?', ['%'.$transactionNumber.'%']),
            ))
            ->when($this->taxClassification !== '', fn ($query) => $query->where('tax_classification', $this->taxClassification))
            ->when($this->transactionStatus !== '', fn ($query) => $query->whereHas(
                'posTransaction',
                fn ($transaction) => $transaction->where('status', $this->transactionStatus),
            ))
            ->when($this->startDate !== '', fn ($query) => $query->where(
                'completed_at',
                '>=',
                Carbon::parse($this->startDate, 'Asia/Manila')->startOfDay()->utc(),
            ))
            ->when($this->endDate !== '', fn ($query) => $query->where(
                'completed_at',
                '<',
                Carbon::parse($this->endDate, 'Asia/Manila')->addDay()->startOfDay()->utc(),
            ))
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(20);

        [$inputStartDate, $inputEndDate] = $this->inputVatDateRange();
        $inputVatRecords = app(RecordedVatSummary::class)
            ->inputVatRecordsForDateRange($inputStartDate, $inputEndDate, $this->search);
        $inputVatSummary = app(RecordedInputVat::class)->summarize($inputVatRecords);

        return view('livewire.tax-compliance.vat-records', [
            'records' => $records,
            'recordCount' => $records->total(),
            'selectedRecord' => $this->selectedRecordId
                ? PosVatRecord::query()->with('posTransaction.lines')->find($this->selectedRecordId)
                : null,
            'inputVatRecords' => $inputVatRecords,
            'inputVatSummary' => $inputVatSummary,
            'taxClassifications' => $this->taxClassificationOptions(),
            'transactionStatuses' => $this->transactionStatusOptions(),
        ])->layout('layouts.app', ['title' => 'VAT Records']);
    }

    /**
     * @return array<string, string>
     */
    private function taxClassificationOptions(): array
    {
        return TaxClassification::options() + [TaxClassification::MIXED => 'Mixed'];
    }

    /**
     * @return array<string, string>
     */
    private function transactionStatusOptions(): array
    {
        return PosTransaction::query()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->mapWithKeys(fn (string $status): array => [$status => ucfirst($status)])
            ->all();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function inputVatDateRange(): array
    {
        return [
            $this->startDate !== '' ? $this->startDate : now('Asia/Manila')->startOfMonth()->toDateString(),
            $this->endDate !== '' ? $this->endDate : now('Asia/Manila')->endOfMonth()->toDateString(),
        ];
    }

    private function authorizePermission(): void
    {
        abort_unless(auth()->user()?->can('tax.view'), 403);
    }
}
