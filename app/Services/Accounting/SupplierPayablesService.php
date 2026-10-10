<?php

namespace App\Services\Accounting;

use App\Models\AccountingJournal;
use App\Models\Supplier;
use App\Models\SupplierOpeningInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SupplierPayablesService
{
    public function saveSupplier(array $attributes, int $actorId): Supplier
    {
        $data = Validator::make($attributes, [
            'id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:180'],
            'legal_name' => ['nullable', 'string', 'max:180'],
            'tax_identifier' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:4000'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:50'],
        ])->validate();
        $data = collect($data)->map(fn ($value) => is_string($value) ? trim($value) : $value)->all();
        $data['code'] = mb_strtoupper($data['code'], 'UTF-8');

        return DB::transaction(function () use ($data, $actorId): Supplier {
            $supplier = isset($data['id'])
                ? Supplier::whereKey($data['id'])->lockForUpdate()->firstOrFail()
                : new Supplier(['created_by' => $actorId]);
            if ($supplier->exists && $supplier->code !== $data['code']) {
                throw ValidationException::withMessages(['supplierCode' => 'Supplier codes are stable and cannot be changed.']);
            }
            $duplicate = Supplier::where('code', $data['code'])
                ->when(isset($data['id']), fn ($query) => $query->whereKeyNot($supplier->id))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['supplierCode' => 'This supplier code is already in use.']);
            }
            $supplier->fill($data);
            $supplier->updated_by = $actorId;
            $supplier->save();

            return $supplier->refresh();
        });
    }

    public function saveOpeningInvoice(array $attributes, int $actorId): SupplierOpeningInvoice
    {
        $data = Validator::make($attributes, [
            'id' => ['nullable', 'integer', 'exists:supplier_opening_invoices,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'invoice_number' => ['required', 'string', 'max:100'],
            'recognition_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description' => ['required', 'string', 'max:4000'],
            'terms' => ['nullable', 'string', 'max:180'],
        ])->validate();
        $number = trim($data['invoice_number']);
        if ($number === '') {
            throw ValidationException::withMessages(['invoiceNumber' => 'Invoice number is required.']);
        }
        $recognitionDate = CarbonImmutable::createFromFormat('!Y-m-d', $data['recognition_date'], 'Asia/Manila');
        $dueDate = CarbonImmutable::createFromFormat('!Y-m-d', $data['due_date'], 'Asia/Manila');
        if ($dueDate->lessThan($recognitionDate)) {
            throw ValidationException::withMessages(['dueDate' => 'Due date cannot be before the recognition date.']);
        }
        $amountCents = $this->amountInCents($data['amount']);
        if ($amountCents < 1) {
            throw ValidationException::withMessages(['amount' => 'Opening invoice amount must be positive.']);
        }
        $normalized = mb_strtoupper($number, 'UTF-8');

        return DB::transaction(function () use ($data, $actorId, $number, $normalized, $amountCents): SupplierOpeningInvoice {
            $openingJournal = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->lockForUpdate()->first();
            if ($openingJournal?->status === 'posted') {
                throw ValidationException::withMessages(['invoiceNumber' => 'The approved opening supplier schedule is immutable.']);
            }
            $supplier = Supplier::whereKey($data['supplier_id'])->lockForUpdate()->firstOrFail();
            $invoice = isset($data['id'])
                ? SupplierOpeningInvoice::whereKey($data['id'])->lockForUpdate()->firstOrFail()
                : new SupplierOpeningInvoice;
            if ($invoice->exists && $invoice->status !== 'draft') {
                throw ValidationException::withMessages(['invoiceNumber' => 'Posted opening invoices are immutable.']);
            }
            $postedDuplicate = SupplierOpeningInvoice::activePosted()
                ->where('supplier_id', $supplier->id)
                ->where('invoice_number_normalized', $normalized)
                ->when($invoice->exists, fn ($query) => $query->whereKeyNot($invoice->id))
                ->exists();
            if ($postedDuplicate) {
                throw ValidationException::withMessages(['invoiceNumber' => 'This normalized invoice number is already active and posted for this supplier.']);
            }
            $invoice->fill([
                'supplier_id' => $supplier->id,
                'supplier_code_snapshot' => $supplier->code,
                'supplier_name_snapshot' => $supplier->name,
                'supplier_legal_name_snapshot' => $supplier->legal_name,
                'supplier_tax_identifier_snapshot' => $supplier->tax_identifier,
                'supplier_address_snapshot' => $supplier->address,
                'invoice_number' => $number,
                'invoice_number_normalized' => $normalized,
                'recognition_date' => $data['recognition_date'],
                'due_date' => $data['due_date'],
                'amount_cents' => $amountCents,
                'description' => trim($data['description']),
                'terms' => trim($data['terms'] ?? '') ?: null,
                'status' => 'draft',
                'prepared_by' => $actorId,
            ])->save();

            return $invoice->refresh()->load('supplier');
        });
    }

    public function deleteOpeningInvoice(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId): void {
            $openingJournal = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->lockForUpdate()->first();
            if ($openingJournal?->status === 'posted') {
                throw ValidationException::withMessages(['invoice' => 'The approved opening supplier schedule is immutable.']);
            }
            $invoice = SupplierOpeningInvoice::whereKey($invoiceId)->lockForUpdate()->firstOrFail();
            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages(['invoice' => 'Posted opening invoices cannot be deleted.']);
            }
            $invoice->delete();
        });

    }

    public function postOpeningSchedule(AccountingJournal $journal, int $actorId): void
    {
        $postedInvoices = SupplierOpeningInvoice::activePosted()->lockForUpdate()->get();
        $draftInvoices = SupplierOpeningInvoice::where('status', 'draft')->whereNull('reversal_of_id')->lockForUpdate()->get();
        $scheduledInvoices = $postedInvoices->concat($draftInvoices);
        $duplicate = $scheduledInvoices
            ->groupBy(fn (SupplierOpeningInvoice $invoice) => $invoice->supplier_id.':'.$invoice->invoice_number_normalized)
            ->first(fn ($group) => $group->count() > 1);
        if ($duplicate) {
            throw ValidationException::withMessages(['opening' => 'Normalized supplier invoice numbers must be unique among active posted opening invoices.']);
        }
        $invoices = $draftInvoices;
        $apLines = $journal->lines->filter(fn ($line) => $line->account?->classification === 'accounts_payable');
        $apDebitCents = $apLines->sum('debit_cents');
        $apCreditCents = $apLines->sum('credit_cents');
        $scheduleCents = $scheduledInvoices->sum('amount_cents');
        if ($apDebitCents > 0 || $apCreditCents !== $scheduleCents) {
            throw ValidationException::withMessages([
                'opening' => 'Opening supplier invoice schedule must equal the controlled Accounts Payable credit balance exactly.',
            ]);
        }
        $cutoverDate = CarbonImmutable::parse($journal->accounting_date, 'Asia/Manila');
        foreach ($invoices as $invoice) {
            if ($invoice->recognition_date->greaterThan($cutoverDate)) {
                throw ValidationException::withMessages(['opening' => 'An opening supplier invoice cannot be recognized after the cutover date.']);
            }
        }
        if ($invoices->isNotEmpty() && $apLines->isEmpty()) {
            throw ValidationException::withMessages(['opening' => 'Opening supplier invoices require a controlled Accounts Payable credit line.']);
        }
        foreach ($invoices as $invoice) {
            $invoice->forceFill([
                'status' => 'posted',
                'opening_journal_id' => $journal->id,
                'posted_at' => now('UTC'),
                'posted_by' => $actorId,
                'approved_at' => now('UTC'),
                'approved_by' => $actorId,
            ])->save();
        }
    }

    private function amountInCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        if (strlen($whole) > 12) {
            throw ValidationException::withMessages(['amount' => 'Opening invoice amount is too large.']);
        }

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
