<?php

use Joemuigai\LaravelMpesa\Commands\LaravelMpesaCommand;
use Joemuigai\LaravelMpesa\Commands\MpesaComment;
use Joemuigai\LaravelMpesa\Commands\MpesaEnv;
use ReflectionClass;

it('generates config with comments', function () {
    $command = new LaravelMpesaCommand;

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('varExport');

    // Test inline comment
    $comment = new MpesaComment('value', 'This is a comment');
    $output = $method->invoke($command, $comment);
    expect($output)->toBe("'value' // This is a comment");

    // Test block comment in array
    $array = [
        'key' => new MpesaComment('value', 'Block comment', false),
    ];
    $output = $method->invoke($command, $array);
    expect($output)->toContain("// Block comment\n    'key' => 'value',");

    // Test comment with MpesaEnv
    $envComment = new MpesaComment(new MpesaEnv('KEY', 'default'), 'Env comment');
    $output = $method->invoke($command, $envComment);
    expect($output)->toBe("env('KEY', 'default') // Env comment");
});
