<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingPostingPeriod;
use App\Services\Accounting\AccountingPeriodService;
use Carbon\CarbonImmutable;
use Livewire\Component;

class AccountingPeriods extends Component
{
    public int $year;

    public ?int $selectedPeriodId = null;

    public array $dependentPeriodIds = [];

    public string $reason = '';

    public function mount(): void
    {
        $this->year = CarbonImmutable::now('Asia/Manila')->year;
    }

    public function selectForReopen(int $periodId): void
    {
        abort_unless(auth()->user()?->can('accounting.reopen-period'), 403);
        $period = AccountingPostingPeriod::query()->findOrFail($periodId);
        $this->selectedPeriodId = $period->id;
        $this->dependentPeriodIds = [];
    }

    public function close(int $periodId): void
    {
        app(AccountingPeriodService::class)->close($periodId, $this->reason, auth()->user());
        $this->reset('reason');
        session()->flash('period-message', 'Accounting month closed.');
    }

    public function reopen(int $periodId): void
    {
        app(AccountingPeriodService::class)->reopen($periodId, $this->dependentPeriodIds, $this->reason, auth()->user());
        $this->reset('reason', 'dependentPeriodIds', 'selectedPeriodId');
        session()->flash('period-message', 'Selected month and explicitly authorized dependent months reopened. Reconcile and close them chronologically.');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('accounting.view'), 403);
        $availableYears = AccountingPostingPeriod::query()->distinct()->orderByDesc('fiscal_year')
            ->pluck('fiscal_year')->map(fn ($year) => (int) $year)->push(CarbonImmutable::now('Asia/Manila')->year)
            ->unique()->sortDesc()->values();
        if (! $availableYears->contains($this->year)) {
            $this->year = CarbonImmutable::now('Asia/Manila')->year;
        }
        $months = collect(range(1, 12))->map(function (int $month) {
            $date = CarbonImmutable::create($this->year, $month, 1, 0, 0, 0, 'Asia/Manila');
            $period = AccountingPostingPeriod::firstOrCreateForDate($date->toDateString());

            return [
                'period' => $period,
                'readiness' => app(AccountingPeriodService::class)->readiness($period),
            ];
        });

        $selectedPeriod = $this->selectedPeriodId
            ? AccountingPostingPeriod::query()->find($this->selectedPeriodId)
            : null;
        $dependentPeriods = $selectedPeriod
            ? AccountingPostingPeriod::query()->where('book_key', $selectedPeriod->book_key)->where('status', 'closed')
                ->where('starts_on', '>', $selectedPeriod->starts_on)->orderBy('starts_on')->get()
            : collect();

        return view('livewire.accounting.accounting-periods', [
            'availableYears' => $availableYears,
            'months' => $months,
            'selectedPeriod' => $selectedPeriod,
            'dependentPeriods' => $dependentPeriods,
        ])->layout('layouts.app', ['title' => 'Accounting Periods']);
    }
}
