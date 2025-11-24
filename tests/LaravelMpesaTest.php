<?php

use Illuminate\Support\Facades\Http;
use Joemuigai\LaravelMpesa\Facades\LaravelMpesa;

beforeEach(function () {
    config()->set('mpesa.credentials.consumer_key', 'test_key');
    config()->set('mpesa.credentials.consumer_secret', 'test_secret');
    config()->set('mpesa.environment', 'sandbox');
    config()->set('mpesa.base_urls.sandbox', 'https://sandbox.safaricom.co.ke');
});

it('can get access token', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3599,
        ], 200),
    ]);

    $token = LaravelMpesa::getAccessToken();

    expect($token)->toBe('test_token');
});

it('can initiate stk push', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3599,
        ], 200),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => '12345',
            'CheckoutRequestID' => '67890',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success',
            'CustomerMessage' => 'Success',
        ], 200),
    ]);

    config()->set('mpesa.stk.shortcode', '174379');
    config()->set('mpesa.stk.passkey', 'test_passkey');
    config()->set('mpesa.stk.callback_url', 'https://example.com/callback');

    $response = LaravelMpesa::stkPush(100, '0712345678');

    expect($response['ResponseCode'])->toBe('0');
});

it('formats phone number correctly', function () {
    $class = new \Joemuigai\LaravelMpesa\LaravelMpesa;
    $method = new ReflectionMethod($class, 'formatPhoneNumber');

    expect($method->invoke($class, '0712345678'))->toBe('254712345678');
    expect($method->invoke($class, '254712345678'))->toBe('254712345678');
    expect($method->invoke($class, '+254712345678'))->toBe('254712345678');
    expect($method->invoke($class, '712345678'))->toBe('254712345678');

    // New prefixes (011, etc.)
    expect($method->invoke($class, '0112345678'))->toBe('254112345678');
    expect($method->invoke($class, '112345678'))->toBe('254112345678');
    expect($method->invoke($class, '254112345678'))->toBe('254112345678');
    expect($method->invoke($class, '+254112345678'))->toBe('254112345678');
});

it('can register c2b urls', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/c2b/v1/registerurl' => Http::response([
            'OriginatorConversationID' => '12345',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success',
        ], 200),
    ]);

    config()->set('mpesa.c2b.shortcode', '600000');
    config()->set('mpesa.c2b.confirmation_url', 'https://example.com/confirm');
    config()->set('mpesa.c2b.validation_url', 'https://example.com/validate');

    $response = LaravelMpesa::c2bRegisterUrl();

    expect($response['ResponseCode'])->toBe('0');
});

it('can simulate c2b transaction', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/c2b/v1/simulate' => Http::response([
            'OriginatorConversationID' => '12345',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success',
        ], 200),
    ]);

    config()->set('mpesa.c2b.shortcode', '600000');

    $response = LaravelMpesa::c2bSimulate(100, '0712345678');

    expect($response['ResponseCode'])->toBe('0');
});

it('can initiate b2c payment', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/b2c/v3/paymentrequest' => Http::response([
            'ConversationID' => '12345',
            'OriginatorConversationID' => '67890',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ], 200),
    ]);

    config()->set('mpesa.b2c.shortcode', '600000');
    config()->set('mpesa.initiator.name', 'testapi');
    config()->set('mpesa.initiator.password', 'password');
    config()->set('mpesa.security.certificates.sandbox', __DIR__.'/../src/Certificates/SandboxCertificate.cer');

    $response = LaravelMpesa::b2c(100, '0712345678');

    expect($response['ResponseCode'])->toBe('0');
});

it('can check transaction status', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/transactionstatus/v1/query' => Http::response([
            'ConversationID' => '12345',
            'OriginatorConversationID' => '67890',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ], 200),
    ]);

    config()->set('mpesa.transaction_status.party_a', '600000');
    config()->set('mpesa.initiator.name', 'testapi');
    config()->set('mpesa.initiator.password', 'password');
    config()->set('mpesa.security.certificates.sandbox', __DIR__.'/../src/Certificates/SandboxCertificate.cer');

    $response = LaravelMpesa::transactionStatus('LGR0000000');

    expect($response['ResponseCode'])->toBe('0');
});

it('can check account balance', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/accountbalance/v1/query' => Http::response([
            'ConversationID' => '12345',
            'OriginatorConversationID' => '67890',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ], 200),
    ]);

    config()->set('mpesa.transaction_status.party_a', '600000');
    config()->set('mpesa.initiator.name', 'testapi');
    config()->set('mpesa.initiator.password', 'password');
    config()->set('mpesa.security.certificates.sandbox', __DIR__.'/../src/Certificates/SandboxCertificate.cer');

    $response = LaravelMpesa::accountBalance();

    expect($response['ResponseCode'])->toBe('0');
});

it('can reverse transaction', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/reversal/v1/request' => Http::response([
            'ConversationID' => '12345',
            'OriginatorConversationID' => '67890',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ], 200),
    ]);

    config()->set('mpesa.c2b.shortcode', '600000');
    config()->set('mpesa.initiator.name', 'testapi');
    config()->set('mpesa.initiator.password', 'password');
    config()->set('mpesa.security.certificates.sandbox', __DIR__.'/../src/Certificates/SandboxCertificate.cer');

    $response = LaravelMpesa::reversal('LGR0000000', 100, '600000');

    expect($response['ResponseCode'])->toBe('0');
});

it('can generate dynamic qr code', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/qrcode/v1/generate' => Http::response([
            'QRCode' => 'test_qr_code_string',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success',
        ], 200),
    ]);

    config()->set('mpesa.initiator.name', 'testapi');

    $response = LaravelMpesa::dynamicQr(100, 'REF123', 'PB', '600000');

    expect($response['ResponseCode'])->toBe('0');
    expect($response['QRCode'])->toBe('test_qr_code_string');
});

it('can register pull transaction urls', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/pulltransactions/v1/register' => Http::response([
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success',
        ], 200),
    ]);

    config()->set('mpesa.pull.shortcode', '600000');
    config()->set('mpesa.pull.register.nominated_number', '254712345678');
    config()->set('mpesa.pull.register.callback_url', 'https://example.com/callback');

    $response = LaravelMpesa::pullTransactionRegister();

    expect($response['ResponseCode'])->toBe('0');
});

it('can initiate b2b payment', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/b2b/v1/paymentrequest' => Http::response([
            'ConversationID' => '12345',
            'OriginatorConversationID' => '67890',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ], 200),
    ]);

    config()->set('mpesa.b2c.shortcode', '600000');
    config()->set('mpesa.initiator.name', 'testapi');
    config()->set('mpesa.initiator.password', 'password');
    config()->set('mpesa.security.certificates.sandbox', __DIR__.'/../src/Certificates/SandboxCertificate.cer');
    config()->set('mpesa.callbacks.b2b.result', 'https://example.com/result');
    config()->set('mpesa.callbacks.b2b.timeout', 'https://example.com/timeout');

    $response = LaravelMpesa::b2b(100, '600001');

    expect($response['ResponseCode'])->toBe('0');
});

it('can query stk push status', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/stkpushquery/v1/query' => Http::response([
            'ResponseCode' => '0',
            'ResponseDescription' => 'The service request has been accepted successfully',
            'MerchantRequestID' => '12345',
            'CheckoutRequestID' => '67890',
            'ResultCode' => '0',
            'ResultDesc' => 'The service request is processed successfully.',
        ], 200),
    ]);

    config()->set('mpesa.stk.shortcode', '174379');
    config()->set('mpesa.stk.passkey', 'test_passkey');

    $response = LaravelMpesa::stkPushQuery('ws_CO_DMZ_12345');

    expect($response['ResponseCode'])->toBe('0');
    expect($response['ResultCode'])->toBe('0');
});

it('uses paybill transaction type by default', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => '12345',
            'CheckoutRequestID' => '67890',
            'ResponseCode' => '0',
        ], 200),
    ]);

    config()->set('mpesa.stk.shortcode', '174379');
    config()->set('mpesa.stk.passkey', 'test_passkey');
    config()->set('mpesa.stk.callback_url', 'https://example.com/callback');
    config()->set('mpesa.stk.transaction_types.paybill', 'CustomerPayBillOnline');

    LaravelMpesa::stkPush(100, '0712345678');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['TransactionType']) && $body['TransactionType'] === 'CustomerPayBillOnline';
    });
});

it('uses buy goods transaction type with withBuyGoods', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => '12345',
            'CheckoutRequestID' => '67890',
            'ResponseCode' => '0',
        ], 200),
    ]);

    config()->set('mpesa.stk.shortcode', '600001');
    config()->set('mpesa.stk.passkey', 'test_passkey');
    config()->set('mpesa.stk.callback_url', 'https://example.com/callback');

    LaravelMpesa::withBuyGoods()->stkPush(100, '0712345678');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['TransactionType']) && $body['TransactionType'] === 'CustomerBuyGoodsOnline';
    });
});

it('uses paybill transaction type with withPaybill', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => '12345',
            'CheckoutRequestID' => '67890',
            'ResponseCode' => '0',
        ], 200),
    ]);

    config()->set('mpesa.stk.shortcode', '174379');
    config()->set('mpesa.stk.passkey', 'test_passkey');
    config()->set('mpesa.stk.callback_url', 'https://example.com/callback');

    LaravelMpesa::withPaybill()->stkPush(100, '0712345678');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['TransactionType']) && $body['TransactionType'] === 'CustomerPayBillOnline';
    });
});

it('uses transaction type from method parameter', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => '12345',
            'CheckoutRequestID' => '67890',
            'ResponseCode' => '0',
        ], 200),
    ]);

    config()->set('mpesa.stk.shortcode', '600001');
    config()->set('mpesa.stk.passkey', 'test_passkey');
    config()->set('mpesa.stk.callback_url', 'https://example.com/callback');
    config()->set('mpesa.stk.transaction_types.buy_goods', 'CustomerBuyGoodsOnline');

    LaravelMpesa::stkPush(100, '0712345678', 'REF001', 'Payment', 'buy_goods');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['TransactionType']) && $body['TransactionType'] === 'CustomerBuyGoodsOnline';
    });
});

it('method parameter overrides runtime override', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => '12345',
            'CheckoutRequestID' => '67890',
            'ResponseCode' => '0',
        ], 200),
    ]);

    config()->set('mpesa.stk.shortcode', '174379');
    config()->set('mpesa.stk.passkey', 'test_passkey');
    config()->set('mpesa.stk.callback_url', 'https://example.com/callback');
    config()->set('mpesa.stk.transaction_types.paybill', 'CustomerPayBillOnline');

    // Runtime override says buy_goods, but parameter says paybill
    LaravelMpesa::withBuyGoods()->stkPush(100, '0712345678', 'REF001', 'Payment', 'paybill');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['TransactionType']) && $body['TransactionType'] === 'CustomerPayBillOnline';
    });
});

it('can chain transaction type with other overrides', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'test_token', 'expires_in' => 3600], 200),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => '12345',
            'CheckoutRequestID' => '67890',
            'ResponseCode' => '0',
        ], 200),
    ]);

    config()->set('mpesa.stk.passkey', 'test_passkey');
    config()->set('mpesa.stk.callback_url', 'https://example.com/callback');

    LaravelMpesa::withBuyGoods()
        ->withShortcode('600001')
        ->stkPush(100, '0712345678');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['TransactionType']) && isset($body['BusinessShortCode'])
            && $body['TransactionType'] === 'CustomerBuyGoodsOnline'
            && $body['BusinessShortCode'] === '600001';
    });
});
