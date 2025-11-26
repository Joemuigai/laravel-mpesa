<?php

use Joemuigai\LaravelMpesa\Services\CallbackParser;

it('parses STK Push callbacks correctly', function () {
    $parser = new CallbackParser;

    $payload = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => '29115-34620561-1',
                'CheckoutRequestID' => 'ws_CO_191220191020363925',
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 100],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ7RT61SV'],
                        ['Name' => 'PhoneNumber', 'Value' => '254712345678'],
                        ['Name' => 'TransactionDate', 'Value' => 20191219102115],
                    ],
                ],
            ],
        ],
    ];

    $result = $parser->parse('stk_push', $payload);

    expect($result)->toHaveKeys([
        'merchant_request_id',
        'checkout_request_id',
        'result_code',
        'result_desc',
        'amount',
        'mpesa_receipt_number',
        'phone_number',
        'transaction_date',
    ]);

    expect($result['merchant_request_id'])->toBe('29115-34620561-1');
    expect($result['amount'])->toBe(100);
    expect($result['mpesa_receipt_number'])->toBe('NLJ7RT61SV');
});

it('parses C2B callbacks correctly', function () {
    $parser = new CallbackParser;

    $payload = [
        'TransactionType' => 'Pay Bill',
        'TransID' => 'NLJ7RT61SV',
        'TransTime' => '20191219102115',
        'TransAmount' => '10.00',
        'BusinessShortCode' => '600000',
        'BillRefNumber' => 'account123',
        'MSISDN' => '254712345678',
        'FirstName' => 'John',
        'LastName' => 'Doe',
    ];

    $result = $parser->parse('c2b_confirmation', $payload);

    expect($result)->toHaveKeys([
        'transaction_type',
        'trans_id',
        'trans_amount',
        'business_short_code',
        'bill_ref_number',
        'msisdn',
        'first_name',
        'last_name',
    ]);

    expect($result['trans_id'])->toBe('NLJ7RT61SV');
    expect($result['trans_amount'])->toBe('10.00');
});

it('parses B2C callbacks correctly', function () {
    $parser = new CallbackParser;

    $payload = [
        'Result' => [
            'ConversationID' => 'AG_20191219_00005797af5d7d75f652',
            'OriginatorConversationID' => '8551-67195589-1',
            'ResultCode' => 0,
            'ResultDesc' => 'The service request is  processed successfully.',
            'TransactionID' => 'NLJ7RT61SV',
            'ResultParameters' => [
                'ResultParameter' => [
                    ['Key' => 'TransactionReceipt', 'Value' => 'NLJ7RT61SV'],
                    ['Key' => 'TransactionAmount', 'Value' => 100],
                ],
            ],
        ],
    ];

    $result = $parser->parse('b2c_result', $payload);

    expect($result)->toHaveKeys([
        'conversation_id',
        'originator_conversation_id',
        'result_code',
        'result_desc',
        'transaction_id',
    ]);

    expect($result['conversation_id'])->toBe('AG_20191219_00005797af5d7d75f652');
    expect($result['result_code'])->toBe(0);
});

it('throws exception for unknown callback type', function () {
    $parser = new CallbackParser;

    $parser->parse('unknown_type', []);
})->throws(InvalidArgumentException::class);
