<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class IncludeMissingPosVatRecords
{
    public function handle(): int
    {
        $created = 0;

        DB::table('pos_transactions')
            ->where('status', 'completed')
            ->orderBy('id')
            ->chunkById(500, function ($transactions) use (&$created): void {
                $now = now();
                $records = $transactions->map(fn (object $transaction): array => [
                    'pos_transaction_id' => $transaction->id,
                    'taxable_sales' => $transaction->subtotal,
                    'vat_rate' => $transaction->vat_rate,
                    'output_vat' => $transaction->vat_amount,
                    'total' => $transaction->total,
                    'completed_at' => $transaction->completed_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                $created += DB::table('pos_vat_records')->insertOrIgnore($records);
            });

        return $created;
    }
}
