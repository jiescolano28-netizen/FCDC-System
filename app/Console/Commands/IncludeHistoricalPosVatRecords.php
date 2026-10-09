<?php

namespace App\Console\Commands;

use App\Services\IncludeMissingPosVatRecords;
use Illuminate\Console\Command;

class IncludeHistoricalPosVatRecords extends Command
{
    protected $signature = 'tax:include-historical-pos-vat-records';

    protected $description = 'Create missing VAT records for completed POS transactions';

    public function handle(IncludeMissingPosVatRecords $records): int
    {
        $created = $records->handle();
        $this->info("Created {$created} missing POS VAT record(s).");

        return self::SUCCESS;
    }
}
