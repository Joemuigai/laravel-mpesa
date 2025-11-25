<?php

use Joemuigai\LaravelMpesa\Commands\LaravelMpesaCommand;
use ReflectionClass;

it('generates comprehensive env variables for STK push', function () {
    $command = new LaravelMpesaCommand;

    // Set selected APIs using reflection
    $reflection = new ReflectionClass($command);

    $apisProperty = $reflection->getProperty('selectedApis');
    $apisProperty->setValue($command, ['stk']);

    $scenarioProperty = $reflection->getProperty('scenario');
    $scenarioProperty->setValue($command, 'single');

    $txnTypeProperty = $reflection->getProperty('transactionTypePreference');
    $txnTypeProperty->setValue($command, 'paybill');

    $method = $reflection->getMethod('buildEnvVariables');

    $output = $method->invoke($command);

    // Check for core credentials
    expect($output)->toContain('MPESA_CONSUMER_KEY=');
    expect($output)->toContain('MPESA_CONSUMER_SECRET=');

    // Check for common defaults
    expect($output)->toContain('MPESA_BUSINESS_SHORTCODE=');
    expect($output)->toContain('MPESA_CALLBACK_URL=');

    // Check for STK specific vars
    expect($output)->toContain('MPESA_STK_SHORTCODE=');
    expect($output)->toContain('MPESA_STK_PASSKEY=');
    expect($output)->toContain('MPESA_STK_CALLBACK_URL=');
    expect($output)->toContain('MPESA_STK_DEFAULT_ACCOUNT_REF=Payment');
    expect($output)->toContain('MPESA_STK_DEFAULT_DESC=Payment');
    expect($output)->toContain('MPESA_STK_DEFAULT_TYPE=paybill');

    // Check for HTTP config
    expect($output)->toContain('MPESA_HTTP_TIMEOUT=30');
});

it('generates comprehensive env variables for all APIs', function () {
    $command = new LaravelMpesaCommand;

    $reflection = new ReflectionClass($command);

    $apisProperty = $reflection->getProperty('selectedApis');
    $apisProperty->setValue($command, [
        'stk',
        'c2b',
        'b2c',
        'b2b',
        'transaction_status',
        'account_balance',
        'reversal',
        'pull_transaction',
    ]);

    $scenarioProperty = $reflection->getProperty('scenario');
    $scenarioProperty->setValue($command, 'multi_tenant');

    $txnTypeProperty = $reflection->getProperty('transactionTypePreference');
    $txnTypeProperty->setValue($command, 'both');

    $method = $reflection->getMethod('buildEnvVariables');

    $output = $method->invoke($command);

    // Multi-tenant
    expect($output)->toContain('MPESA_ACCOUNT_DRIVER=database');

    // STK
    expect($output)->toContain('MPESA_STK_DEFAULT_TYPE=paybill  # or buy_goods');

    // C2B
    expect($output)->toContain('MPESA_C2B_RESPONSE_TYPE=Completed');

    // B2C
    expect($output)->toContain('MPESA_B2C_DEFAULT_REMARKS=B2C Payment');

    // Status
    expect($output)->toContain('MPESA_TXN_STATUS_PARTY_A=');

    // Pull
    expect($output)->toContain('MPESA_PULL_REQUEST_TYPE=Pull');

    // Security
    expect($output)->toContain('MPESA_SECURITY_CREDENTIAL_CACHE_TTL=3600');
});
