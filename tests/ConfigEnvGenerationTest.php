<?php

use Joemuigai\LaravelMpesa\Commands\LaravelMpesaCommand;
use ReflectionClass;

it('generates config with env function calls', function () {
    $command = new LaravelMpesaCommand;

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('varExport');

    $testArray = [
        'key1' => new \Joemuigai\LaravelMpesa\Commands\MpesaEnv('TEST_KEY', 'default_value'),
        'key2' => new \Joemuigai\LaravelMpesa\Commands\MpesaEnv('ANOTHER_KEY'),
        'nested' => [
            'inner' => new \Joemuigai\LaravelMpesa\Commands\MpesaEnv('NESTED_KEY', 'nested_default'),
        ],
    ];

    $output = $method->invoke($command, $testArray);

    expect($output)->toContain("env('TEST_KEY', 'default_value')");
    expect($output)->toContain("env('ANOTHER_KEY', NULL)");
    expect($output)->toContain("env('NESTED_KEY', 'nested_default')");
});
