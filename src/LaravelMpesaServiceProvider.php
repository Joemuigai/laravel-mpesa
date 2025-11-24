<?php

namespace Joemuigai\LaravelMpesa;

use Joemuigai\LaravelMpesa\Commands\LaravelMpesaCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelMpesaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-mpesa')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_mpesa_accounts_table')
            ->hasCommand(LaravelMpesaCommand::class);
    }

    public function packageRegistered(): void
    {
        // Register LaravelMpesa as a singleton
        $this->app->singleton(LaravelMpesa::class, function ($app) {
            return new LaravelMpesa;
        });

        // Register facade alias
        $this->app->alias(LaravelMpesa::class, 'laravel-mpesa');
    }

    public function packageBooted(): void
    {
        // Additional boot logic can be added here if needed
    }
}
