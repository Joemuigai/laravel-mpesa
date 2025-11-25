<?php

use Joemuigai\LaravelMpesa\Commands\LaravelMpesaCommand;
use Joemuigai\LaravelMpesa\Commands\MpesaEnv;
use ReflectionClass;

it('generates config with env function calls', function () {
    $command = new LaravelMpesaCommand;

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('varExport');
    $method->setAccessible(true);

    // Test simple env call
    $env = new MpesaEnv('TEST_KEY', 'default_value');
    $output = $method->invoke($command, $env);
    expect($output)->toBe("env('TEST_KEY', 'default_value')");

    // Test nested env call
    $nestedEnv = new MpesaEnv('PRIMARY_KEY', new MpesaEnv('FALLBACK_KEY', 'final_default'));
    $output = $method->invoke($command, $nestedEnv);
    expect($output)->toBe("env('PRIMARY_KEY', env('FALLBACK_KEY', 'final_default'))");

    // Test array with env calls
    $array = [
        'key' => new MpesaEnv('ENV_VAR', 'default'),
    ];
    $output = $method->invoke($command, $array);
    expect($output)->toContain("'key' => env('ENV_VAR', 'default')");
});
