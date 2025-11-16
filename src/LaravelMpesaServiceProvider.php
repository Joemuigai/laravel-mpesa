<?php

namespace Joemuigai\LaravelMpesa;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Joemuigai\LaravelMpesa\Commands\LaravelMpesaCommand;

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
            ->hasMigration('create_laravel_mpesa_table')
            ->hasCommand(LaravelMpesaCommand::class);
    }
}
