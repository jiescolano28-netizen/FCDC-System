<?php

namespace Database\Seeders;

use App\Support\DemoDatabaseIsolation;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;

/**
 * Compatibility wrapper for the former proposed-chart command.
 *
 * The application now uses the official chart of accounts. The class and command
 * name are retained so existing demo tooling keeps working, but both apply the
 * official account definitions.
 */
class ProposedChartOfAccountsSeeder extends Seeder
{
    /**
     * @return array{created: list<string>, updated: list<string>, exact_matches: list<string>, conflicts: array<string, mixed>, preserved_unrelated: list<array{code: string, name: string}>, legacy_accounts: list<array{original: string, legacy: string}>}
     */
    public function seed(DemoDatabaseIsolation $isolation): array
    {
        $isolation->verify();

        return app(OfficialChartOfAccountsSeeder::class)->seed();
    }

    public function run(DemoDatabaseIsolation $isolation): void
    {
        $this->reportResult($this->seed($isolation), $this->command);
    }

    public function reportResult(array $result, ?Command $command): bool
    {
        if ($command === null) {
            return $result['conflicts'] === [];
        }

        $command->info('Created: '.count($result['created']).' ['.implode(', ', $result['created']).']');
        $command->info('Updated: '.count($result['updated']).' ['.implode(', ', $result['updated']).']');
        $command->info('Exact matches: '.count($result['exact_matches']).' ['.implode(', ', $result['exact_matches']).']');
        $command->info('Retired non-official accounts: '.count($result['preserved_unrelated']));
        foreach ($result['preserved_unrelated'] as $account) {
            $command->line($account['code'].' '.$account['name']);
        }
        foreach ($result['legacy_accounts'] as $legacy) {
            $command->warn("Used account {$legacy['original']} was preserved as {$legacy['legacy']} for historical reporting.");
        }

        return $result['conflicts'] === [];
    }
}
