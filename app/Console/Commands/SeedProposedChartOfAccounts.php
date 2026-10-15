<?php

namespace App\Console\Commands;

use App\Support\DemoDatabaseIsolation;
use Database\Seeders\ProposedChartOfAccountsSeeder;
use Illuminate\Console\Command;
use RuntimeException;

class SeedProposedChartOfAccounts extends Command
{
    protected $signature = 'demo:seed-proposed-chart';

    protected $description = 'Seed the proposed chart on a verified isolated demo database.';

    public function handle(ProposedChartOfAccountsSeeder $seeder, DemoDatabaseIsolation $isolation): int
    {
        try {
            $result = $seeder->seed($isolation);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return $seeder->reportResult($result, $this) ? self::SUCCESS : self::FAILURE;
    }
}
