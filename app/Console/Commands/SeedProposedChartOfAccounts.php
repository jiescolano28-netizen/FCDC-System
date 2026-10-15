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

        $this->reportCodes('Created', $result['created']);
        $this->reportCodes('Exact matches', $result['exact_matches']);

        $this->components->info('Preserved unrelated/supporting accounts: '.count($result['preserved_unrelated']));
        foreach ($result['preserved_unrelated'] as $account) {
            $this->line($account['code'].' '.$account['name']);
        }

        if ($result['conflicts'] !== []) {
            $this->components->error('Conflicts found; no proposed account was changed or inserted.');
            foreach ($result['conflicts'] as $code => $fields) {
                foreach ($fields as $field => $values) {
                    $this->line($code.' '.$field.': expected '.json_encode($values['expected']).'; found '.json_encode($values['existing']));
                }
            }

            return self::FAILURE;
        }

        return self::SUCCESS;

    }

    private function reportCodes(string $label, array $codes): void
    {
        $this->components->info($label.': '.count($codes));
        if ($codes !== []) {
            $this->line(implode(', ', $codes));
        }
    }
}
