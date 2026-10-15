<?php

namespace App\Console\Commands;

use App\Support\DemoDatabaseIsolation;
use Illuminate\Console\Command;

class VerifyDemoDatabaseIsolation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:verify-isolation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify that the effective application and audit database targets are isolated for demo setup.';

    /**
     * Execute the console command.
     */
    public function handle(DemoDatabaseIsolation $isolation): int
    {
        try {
            $isolation->verify();
        } catch (\RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Demo database isolation verified.');

        return self::SUCCESS;
    }
}
