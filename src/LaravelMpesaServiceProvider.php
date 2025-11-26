<?php

namespace Joemuigai\LaravelMpesa;

use Illuminate\Routing\Router;
use Joemuigai\LaravelMpesa\Commands\LaravelMpesaCommand;
use Joemuigai\LaravelMpesa\Commands\SimulateCallbackCommand;
use Joemuigai\LaravelMpesa\Http\Middleware\VerifyMpesaCallback;
use Joemuigai\LaravelMpesa\Services\CallbackParser;
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
            ->hasMigration('create_laravel_mpesa_tables')
            ->hasCommands([
                LaravelMpesaCommand::class,
                SimulateCallbackCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register LaravelMpesa as a singleton
        $this->app->singleton(LaravelMpesa::class, function ($app) {
            return new LaravelMpesa;
        });

        // Register facade alias
        $this->app->alias(LaravelMpesa::class, 'laravel-mpesa');

        // Register CallbackParser service
        $this->app->singleton(CallbackParser::class, function ($app) {
            return new CallbackParser;
        });
    }

    public function packageBooted(): void
    {
        // Register middleware
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('mpesa.verify', VerifyMpesaCallback::class);

        // Register publishable resources
        if ($this->app->runningInConsole()) {
            // Controller
            $this->publishes([
                __DIR__ . '/../stubs/Controllers/MpesaCallbackController.stub' => app_path('Http/Controllers/MpesaCallbackController.php'),
            ], 'laravel-mpesa-controller');

            // Routes
            $this->publishes([
                __DIR__ . '/../stubs/routes/mpesa-callbacks.stub' => base_path('routes/mpesa.php'),
            ], 'laravel-mpesa-routes');

            // Documentation
            $this->publishes([
                __DIR__ . '/../stubs/CALLBACKS_SETUP.md' => base_path('CALLBACKS_SETUP.md'),
            ], 'laravel-mpesa-docs');

            // Publish events
            $this->publishes([
                __DIR__ . '/Events' => app_path('Events/Mpesa'),
            ], 'mpesa-events');

            // Publish config (alternative to hasConfigFile)
            $this->publishes([
                __DIR__ . '/../config/mpesa.php' => config_path('mpesa.php'),
            ], 'mpesa-config');

            // Publish all stubs
            $this->publishes([
                __DIR__ . '/../stubs' => base_path('stubs/mpesa'),
            ], 'mpesa-stubs');

            // Publish Service Class
            $this->publishes([
                __DIR__ . '/../stubs/Services/MpesaService.stub' => app_path('Services/Mpesa/MpesaService.php'),
            ], 'mpesa-service');
        }
    }
}
