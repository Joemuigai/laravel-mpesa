<?php

namespace Tests;

use Illuminate\Support\Facades\File;
use Joemuigai\LaravelMpesa\LaravelMpesaServiceProvider;
use Orchestra\Testbench\TestCase;

class MigrationPublishingTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [LaravelMpesaServiceProvider::class];
    }

    public function test_migration_can_be_published()
    {
        // Clean up any existing migration
        $migrationFiles = File::glob(database_path('migrations/*_create_laravel_mpesa_tables.php'));
        foreach ($migrationFiles as $file) {
            File::delete($file);
        }

        $this->artisan('vendor:publish', [
            '--tag' => 'laravel-mpesa-migrations',
            '--provider' => 'Joemuigai\LaravelMpesa\LaravelMpesaServiceProvider',
        ])->assertExitCode(0);

        $publishedMigrations = File::glob(database_path('migrations/*_create_laravel_mpesa_tables.php'));

        $this->assertCount(1, $publishedMigrations, 'Migration file was not published.');
    }
}
