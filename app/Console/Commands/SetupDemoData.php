<?php

namespace App\Console\Commands;

use App\Support\DemoDatabaseIsolation;
use Database\Seeders\FullDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SetupDemoData extends Command
{
    protected $signature = 'demo:setup';

    protected $description = 'Migrate and populate a verified isolated demo database with complete synthetic workflows';

    public function handle(DemoDatabaseIsolation $isolation): int
    {
        try {
            // Verify before migrations as well as inside the seeder.
            $isolation->verify();
            if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }

            return $this->call('db:seed', ['--class' => FullDemoSeeder::class, '--force' => true]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
        }

        return self::FAILURE;
    }
}
