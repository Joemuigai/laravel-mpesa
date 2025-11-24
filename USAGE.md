# Usage Guide for Laravel M-Pesa

This document provides detailed usage examples, expected responses, callbacks, and payloads for each of the supported M-Pesa APIs.

---

## 1. STK Push (Lipa Na M-Pesa Online)

### Usage

```php
$response = LaravelMpesa::stkPush(
    amount: 1500,
    phoneNumber: '0712345678',
    reference: 'INV-1001',
    description: 'Invoice payment'
);
```

### With Paybill (default)

```php
$response = LaravelMpesa::withPaybill()
    ->stkPush(1500, '0712345678', 'INV-1001');
```

### With Till (Buy Goods)

```php
$response = LaravelMpesa::withBuyGoods()
    ->stkPush(1500, '0712345678');
```

### Expected Response

```php
[
    'MerchantRequestID' => '29115-34620561-1',
    'CheckoutRequestID' => 'ws_CO_191220191020363925',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Success. Request accepted for processing',
    'CustomerMessage' => 'Success. Request accepted for processing'
]
```

### Callback (Route)

```php
Route::post('/mpesa/stk-callback', function (Request $request) {
    $data = $request->all();
    if (isset($data['Body']['stkCallback'])) {
        $callback = $data['Body']['stkCallback'];
        if ($callback['ResultCode'] == 0) {
            $items = $callback['CallbackMetadata']['Item'];
            $amount = $items[0]['Value'];
            $mpesaReceipt = $items[1]['Value'];
            $transactionDate = $items[2]['Value'];
            $phoneNumber = $items[3]['Value'];
            // Update database
        }
    }
    return response()->json(['ResultCode' => 0]);
});
```

#### Success Payload

```php
[ 'Body' => [
    'stkCallback' => [
        'MerchantRequestID' => '29115-34620561-1',
        'CheckoutRequestID' => 'ws_CO_191220191020363925',
        'ResultCode' => 0,
        'ResultDesc' => 'The service request is processed successfully.',
        'CallbackMetadata' => [
            'Item' => [
                ['Name' => 'Amount', 'Value' => 100.00],
                ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ7RT61SV'],
                ['Name' => 'TransactionDate', 'Value' => 20191219102115],
                ['Name' => 'PhoneNumber', 'Value' => 254712345678]
            ]
        ]
    ]
] ]
```

#### Failed Payload (common codes)

```php
[ 'Body' => [
    'stkCallback' => [
        'MerchantRequestID' => '29115-34620561-1',
        'CheckoutRequestID' => 'ws_CO_191220191020363925',
        'ResultCode' => 1032,
        'ResultDesc' => 'Request cancelled by user'
    ]
] ]
```

---

## 2. STK Push Query

### Usage

```php
$status = LaravelMpesa::stkPushQuery('ws_CO_DMZ_123456');
```

### Expected Response

```php
[
    'ResponseCode' => '0',
    'ResponseDescription' => 'The service request has been accepted successfully',
    'MerchantRequestID' => '29115-34620561-1',
    'CheckoutRequestID' => 'ws_CO_191220191020363925',
    'ResultCode' => '0',
    'ResultDesc' => 'The service request is processed successfully.'
]
```

### Callback

No callback – synchronous response only.

---

## 3. C2B (Customer to Business)

### Register URLs & Simulate

```php
LaravelMpesa::c2bRegisterUrl(
    validationUrl: 'https://yourdomain.com/mpesa/validate',
    confirmationUrl: 'https://yourdomain.com/mpesa/confirm'
);

LaravelMpesa::c2bSimulate(
    amount: 100,
    phoneNumber: '0712345678',
    billRefNumber: 'ACCOUNT123'
);
```

### Register URL Response

```php
[
    'OriginatorCoversationID' => 'AG_20191219_00005797af5d7d75f652',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Success'
]
```

### Simulate Response

```php
[
    'OriginatorCoversationID' => 'AG_20191219_00005797af5d7d75f652',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Accept the service request successfully.'
]
```

### Validation Callback

```php
Route::post('/mpesa/validate', function (Request $request) {
    return response()->json([
        'ResultCode' => 0,
        'ResultDesc' => 'Accepted'
    ]);
});
```

#### Validation Payload

```php
[
    'TransactionType' => 'Pay Bill',
    'TransID' => 'NLJ7RT61SV',
    'TransTime' => '20191122063845',
    'TransAmount' => '100.00',
    'BusinessShortCode' => '600000',
    'BillRefNumber' => 'ACCOUNT123',
    'MSISDN' => '254712345678',
    'FirstName' => 'John',
    'LastName' => 'Doe'
]
```

### Confirmation Callback

```php
Route::post('/mpesa/confirm', function (Request $request) {
    Payment::create($request->all());
    return response()->json(['ResultCode' => 0]);
});
```

#### Confirmation Payload

```php
[
    'TransactionType' => 'Pay Bill',
    'TransID' => 'NLJ7RT61SV',
    'TransAmount' => '100.00',
    'BusinessShortCode' => '600000',
    'BillRefNumber' => 'ACCOUNT123',
    'OrgAccountBalance' => '10000.00',
    'MSISDN' => '254712345678',
    'FirstName' => 'John',
    'LastName' => 'Doe'
]
```

---

## 4. B2C (Business to Customer)

### Usage

```php
$response = LaravelMpesa::b2c([
    'initiator_name' => env('MPESA_INITIATOR_NAME'),
    'security_credential' => LaravelMpesa::generateSecurityCredential(),
    'command_id' => 'BusinessPayment',
    'amount' => 100,
    'party_a' => env('MPESA_B2C_SHORTCODE'),
    'party_b' => '0712345678',
    'remarks' => 'Salary payment',
    'occasion' => 'Monthly salary'
]);
```

### Expected Response

```php
[
    'ConversationID' => 'AG_20191219_00005797af5d7d75f652',
    'OriginatorConversationID' => '16740-34861180-1',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Accept the service request successfully.'
]
```

### Result URL Callback

```php
Route::post('/mpesa/b2c-result', function (Request $request) {
    $result = $request->input('Result');
    if ($result['ResultCode'] == 0) {
        $params = collect($result['ResultParameters']['ResultParameter'])
            ->pluck('Value', 'Key');
        $receipt = $params['TransactionReceipt'];
        $amount = $params['TransactionAmount'];
    }
    return response()->json(['ResultCode' => 0]);
});
```

#### Success Payload

```php
[ 'Result' => [
    'ResultCode' => 0,
    'ResultDesc' => 'The service request is processed successfully.',
    'TransactionID' => 'NLJ41HAY6Q',
    'ResultParameters' => [
        'ResultParameter' => [
            ['Key' => 'TransactionAmount', 'Value' => 100],
            ['Key' => 'TransactionReceipt', 'Value' => 'NLJ41HAY6Q'],
            ['Key' => 'B2CRecipientIsRegisteredCustomer', 'Value' => 'Y'],
            ['Key' => 'TransactionCompletedDateTime', 'Value' => '19.12.2019 10:45:50'],
            ['Key' => 'ReceiverPartyPublicName', 'Value' => '254712345678 - John Doe'],
            ['Key' => 'B2CWorkingAccountAvailableFunds', 'Value' => 900000.00]
        ]
    ]
] ]
```

---

## 5. B2B (Business to Business)

### Usage

```php
$response = LaravelMpesa::b2b([
    'initiator_name' => env('MPESA_INITIATOR_NAME'),
    'security_credential' => LaravelMpesa::generateSecurityCredential(),
    'command_id' => 'BusinessPayBill',
    'sender_identifier_type' => '4',
    'receiver_identifier_type' => '4',
    'amount' => 5000,
    'party_a' => env('MPESA_B2B_SHORTCODE'),
    'party_b' => '600123',
    'remarks' => 'B2B Transfer',
    'account_reference' => 'REF123',
    'queue_timeout_url' => url('/mpesa/b2b/timeout'),
    'result_url' => url('/mpesa/b2b/result')
]);
```

### Expected Response

```php
[
    'ConversationID' => 'AG_20191219_00005797af5d7d75f652',
    'OriginatorConversationID' => '16740-34861180-1',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Accept the service request successfully.'
]
```

### Result URL Callback

```php
Route::post('/mpesa/b2b-result', function (Request $request) {
    $result = $request->input('Result');
    if ($result['ResultCode'] == 0) {
        // Transfer successful
    }
    return response()->json(['ResultCode' => 0]);
});
```

#### Payload

```php
[ 'Result' => [
    'ResultCode' => 0,
    'ResultDesc' => 'The service request is processed successfully.',
    'TransactionID' => 'NLJ41HAY6Q',
    'ResultParameters' => [
        'ResultParameter' => [
            ['Key' => 'Amount', 'Value' => 5000],
            ['Key' => 'TransCompletedTime', 'Value' => '20191219104550'],
            ['Key' => 'ReceiverPartyPublicName', 'Value' => '600000 - Test Business'],
            ['Key' => 'DebitAccountCurrentBalance', 'Value' => '5000.00']
        ]
    ]
] ]
```

---

## 6. Transaction Status

### Usage

```php
$response = LaravelMpesa::transactionStatus(
    transactionId: 'OEI2AK4Q16',
    partyA: '600000',
    remarks: 'Status query'
);
```

### Expected Response

```php
[
    'OriginatorConversationID' => '16740-34861180-1',
    'ConversationID' => 'AG_20191219_00005797af5d7d75f652',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Accept the service request successfully.'
]
```

### Callback (Result URL)

```php
Route::post('/mpesa/transaction-status', function (Request $request) {
    $result = $request->input('Result');
    return response()->json(['ResultCode' => 0]);
});
```

#### Payload

```php
[ 'Result' => [
    'ResultCode' => 0,
    'TransactionID' => 'NLJ41HAY6Q',
    'ResultParameters' => [
        'ResultParameter' => [
            ['Key' => 'ReceiptNo', 'Value' => 'NLJ41HAY6Q'],
            ['Key' => 'Amount', 'Value' => 100],
            ['Key' => 'TransactionStatus', 'Value' => 'Completed'],
            ['Key' => 'FinalisedTime', 'Value' => 20191219104550],
            ['Key' => 'CreditPartyName', 'Value' => '254712345678 - John Doe']
        ]
    ]
] ]
```

---

## 7. Account Balance

### Usage

```php
$response = LaravelMpesa::accountBalance(
    identifierType: '4',
    remarks: 'Balance check'
);
```

### Expected Response

```php
[
    'OriginatorConversationID' => '16740-34861180-1',
    'ConversationID' => 'AG_20191219_00005797af5d7d75f652',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Accept the service request successfully.'
]
```

### Callback (Result URL)

```php
Route::post('/mpesa/account-balance', function (Request $request) {
    $result = $request->input('Result');
    if ($result['ResultCode'] == 0) {
        $balance = $result['ResultParameters']['ResultParameter'][0]['Value'];
        // Parse balance string
    }
    return response()->json(['ResultCode' => 0]);
});
```

#### Payload

```php
[ 'Result' => [
    'ResultCode' => 0,
    'ResultParameters' => [
        'ResultParameter' => [
            ['Key' => 'AccountBalance', 'Value' => 'Working Account|KES|50000.00|50000.00|0.00|0.00&Float Account|KES|0.00|...'],
            ['Key' => 'BOCompletedTime', 'Value' => 20191219104550]
        ]
    ]
] ]
```

---

## 8. Reversal

### Usage

```php
$response = LaravelMpesa::reversal([
    'initiator_name' => env('MPESA_INITIATOR_NAME'),
    'security_credential' => LaravelMpesa::generateSecurityCredential(),
    'command_id' => 'TransactionReversal',
    'transaction_id' => $originalTransactionId,
    'amount' => $originalAmount,
    'receiver_party' => env('MPESA_STK_SHORTCODE'),
    'receiver_identifier_type' => '4',
    'remarks' => 'Reversal',
    'queue_timeout_url' => url('/mpesa/reversal/timeout'),
    'result_url' => url('/mpesa/reversal/result')
]);
```

### Expected Response

```php
[
    'OriginatorConversationID' => '16740-34861180-1',
    'ConversationID' => 'AG_20191219_00005797af5d7d75f652',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Accept the service request successfully.'
]
```

### Result URL Callback

```php
Route::post('/mpesa/reversal-result', function (Request $request) {
    $result = $request->input('Result');
    if ($result['ResultCode'] == 0) {
        // Reversal successful
    }
    return response()->json(['ResultCode' => 0]);
});
```

#### Success Payload

```php
[ 'Result' => [
    'ResultCode' => 0,
    'ResultDesc' => 'The service request is processed successfully.',
    'TransactionID' => 'NLJ41HAY6Q',
    'ResultParameters' => [
        'ResultParameter' => [
            ['Key' => 'Amount', 'Value' => 100],
            ['Key' => 'OriginalTransactionID', 'Value' => $originalTransactionId],
            ['Key' => 'TransCompletedTime', 'Value' => 20191219104550],
            ['Key' => 'CreditPartyPublicName', 'Value' => '254712345678 - John Doe']
        ]
    ]
] ]
```

---

## 9. Dynamic QR Code

### Usage

```php
$qr = LaravelMpesa::dynamicQr([
    'shortcode' => env('MPESA_STK_SHORTCODE'),
    'amount' => 2500,
    'reference' => 'QR-001',
    'expiry_date' => now()->addHours(2)->toIso8601String()
]);
```

### Expected Response

```php
[
    'ResponseCode' => 'AG_20191219_000043fdf61864fe9ff5',
    'RequestID' => '16738-27456357-1',
    'ResponseDescription' => 'The service request is processed successfully.',
    'QRCode' => 'iVBORw0KGgoAAAANSUhEUgAAAPoAAAD6AQ...'
]
```

### Callback

No callback – synchronous response with QR code.

#### Display QR Code

```php
$qrData = $qr['QRCode'];
echo "<img src='data:image/png;base64,$qrData' />";
```

---

## 10. Pull Transaction

### Usage

```php
$response = LaravelMpesa::pullTransactionRegister([
    'shortcode' => '600000',
    'requestType' => 'Pull',
    'nominatedNumber' => '254712345678'
]);
```

### Expected Response

```php
[
    'OriginatorConversationID' => '16740-34861180-1',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Accept the service request successfully.'
]
```

### Callback

No callback for registration. Use the pull endpoint to retrieve transactions.

---

_Generated on_ `{{date}}` _by_ **Antigravity**.
