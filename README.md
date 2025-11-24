# Laravel M-Pesa

```
 _                               _   __  __       ____
| |                             | | |  \/  |     |  _ \
| |     __ _ _ __ __ ___   _____| | | \  / |_____| |_) | ___  ___  __ _
| |    / _` | '__/ _` \ \ / / _ \ | | |\/| |_____|  __/ / _ \/ __|/ _` |
| |___| (_| | | | (_| |\ V /  __/ | | |  | |     | |   |  __/\__ \ (_| |
|______\__,_|_|  \__,_| \_/ \___|_| |_|  |_|     |_|    \___||___/\__,_|

                              by Joemuigai
```

[![Latest Version on Packagist](https://img.shields.io/packagist/v/joemuigai/laravel-mpesa.svg?style=flat-square)](https://packagist.org/packages/joemuigai/laravel-mpesa)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/joemuigai/laravel-mpesa/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/joemuigai/laravel-mpesa/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/joemuigai/laravel-mpesa.svg?style=flat-square)](https://packagist.org/packages/joemuigai/laravel-mpesa)

A comprehensive, production-ready Laravel package for integrating with Safaricom's M-Pesa Daraja API. Built for both single-merchant applications and multi-tenant SaaS platforms.

## Table of Contents

-   [Features](#features)
-   [Requirements](#requirements)
-   [Installation](#installation)
-   [Configuration](#configuration)
-   [API Reference](#api-reference)
-   [Multi-Tenant Usage](#multi-tenant-usage)
-   [Error Handling](#error-handling)
-   [Production Checklist](#production-checklist)
-   [Usage](#usage)
-   [API Error Codes](#api-error-codes)
-   [Testing](#testing)
-   [Configuration Options](#configuration-options)
-   [Best Practices](#best-practices)
-   [Changelog](#changelog)
-   [Contributing](#contributing)
-   [Security](#security)
-   [Credits](#credits)
-   [License](#license)
-   [Support](#support)

## API Error Codes

For a comprehensive list of error codes returned by each M-Pesa API, see the [API_ERROR_CODES.md](file:///var/www/laravel-mpesa/API_ERROR_CODES.md) file.

## Features

✨ **Complete API Coverage** - 11 M-Pesa APIs supported  
🏢 **Multi-Tenant Ready** - Database-driven account management  
💪 **Production Optimized** - HTTP retries, caching, and failsafe mechanisms  
🎯 **Flexible** - Paybill & Till Number (Buy Goods) support  
🔧 **Developer Friendly** - Interactive installation, IDE autocomplete  
🧪 **Well Tested** - Comprehensive test suite  
📚 **Type Safe** - Full PHPDoc annotations

## Requirements

-   PHP 8.4+
-   Laravel 11.0+ or 12.0+

## Installation

Install via Composer:

```bash
composer require joemuigai/laravel-mpesa
```

Run the interactive installation command:

```bash
php artisan mpesa:install
```

The installation wizard will guide you through:

1. **Scenario Selection** - Single account, multi-tenant, or hybrid
2. **API Selection** - Choose which M-Pesa APIs you'll use
3. **Transaction Type** - Paybill, Till Number, or both
4. **Environment Setup** - Optionally add variables to `.env`

### Manual Installation

If you prefer manual setup:

```bash
# Publish config
php artisan vendor:publish --tag="laravel-mpesa-config"

# Publish migrations (multi-tenant only)
php artisan vendor:publish --tag="laravel-mpesa-migrations"
```

## Configuration

### Environment Variables

Add your M-Pesa credentials to `.env`:

```env
# Core Credentials
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_ENVIRONMENT=sandbox  # or production

# STK Push (Lipa Na M-Pesa Online)
MPESA_STK_SHORTCODE=174379
MPESA_STK_PASSKEY=your_passkey
MPESA_STK_CALLBACK_URL=https://yourdomain.com/mpesa/callback
MPESA_STK_DEFAULT_TYPE=paybill  # or buy_goods

# B2C (optional)
MPESA_B2C_SHORTCODE=600000
MPESA_INITIATOR_NAME=testapi
MPESA_INITIATOR_PASSWORD=your_password

# Multi-Tenant (optional)
MPESA_ACCOUNT_DRIVER=database
MPESA_ACCOUNT_MODEL=App\Models\MpesaAccount
```

### Multi-Tenant Setup

For SaaS platforms supporting multiple merchants:

1. **Run migrations**:

```bash
php artisan migrate
```

2. **Create accounts** in your database:

```php
use App\Models\MpesaAccount;

MpesaAccount::create([
    'tenant_id' => 'tenant-123',
    'name' => 'Client Business',
    'credentials' => [
        'consumer_key' => 'client_key',
        'consumer_secret' => 'client_secret',
        'stk' => [
            'shortcode' => '600001',
            'passkey' => 'client_passkey',
            'callback_url' => 'https://client.com/callback',
            'default_type' => 'till',
        ],
    ],
]);
```

## API Reference

For detailed usage examples, expected responses, callbacks, and payloads for each M-Pesa API, see the dedicated [USAGE.md](file:///var/www/laravel-mpesa/USAGE.md) file.

### 1. STK Push (Lipa Na M-Pesa Online)

Prompt customers to pay via M-Pesa on their phones.

#### Usage

For detailed usage examples, see the dedicated [USAGE.md](file:///var/www/laravel-mpesa/USAGE.md) file.

#### Response

```php
[
    'MerchantRequestID' => '29115-34620561-1',
    'CheckoutRequestID' => 'ws_CO_191220191020363925',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Success. Request accepted for processing',
    'CustomerMessage' => 'Success. Request accepted for processing'
]
```

#### Callback

```php
// routes/web.php
Route::post('/mpesa/stk-callback', function (Request $request) {
    $data = $request->all();

    if (isset($data['Body']['stkCallback'])) {
        $callback = $data['Body']['stkCallback'];

        if ($callback['ResultCode'] == 0) {
            // Payment successful
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

**Success Payload:**

```php
[
    'Body' => [
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
    ]
]
```

**Failed Payload (Common codes: 1032=Cancelled, 1037=Timeout, 2001=Wrong PIN):**

```php
[
    'Body' => [
        'stkCallback' => [
            'MerchantRequestID' => '29115-34620561-1',
            'CheckoutRequestID' => 'ws_CO_191220191020363925',
            'ResultCode' => 1032,
            'ResultDesc' => 'Request cancelled by user'
        ]
    ]
]
```

---

### 2. STK Push Query

Query the status of an STK Push transaction.

#### Usage

```php
$status = LaravelMpesa::stkPushQuery('ws_CO_DMZ_123456');
```

#### Response

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

#### Callback

No callback - synchronous response only.

---

### 3. C2B (Customer to Business)

Receive payments from customers to your paybill or till number.

#### Usage

```php
// Step 1: Register URLs (one-time setup)
$response = LaravelMpesa::c2bRegisterUrl(
    validationUrl: 'https://yourdomain.com/mpesa/validate',
    confirmationUrl: 'https://yourdomain.com/mpesa/confirm'
);

// Step 2: Simulate C2B (sandbox only - for testing)
$response = LaravelMpesa::c2bSimulate(
    amount: 100,
    phoneNumber: '0712345678',
    billRefNumber: 'ACCOUNT123'
);
```

#### Response

**Register URL:**

```php
[
    'OriginatorCoversationID' => 'AG_20191219_00005797af5d7d75f652',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Success'
]
```

**Simulate:**

```php
[
    'OriginatorCoversationID' => 'AG_20191219_00005797af5d7d75f652',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Accept the service request successfully.'
]
```

#### Callbacks

**Validation URL (sent before processing):**

```php
Route::post('/mpesa/validate', function (Request $request) {
    // Verify transaction before accepting
    return response()->json([
        'ResultCode' => 0,  // 0 = Accept, Other = Reject
        'ResultDesc' => 'Accepted'
    ]);
});
```

**Validation Payload:**

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

**Confirmation URL (sent after success):**

```php
Route::post('/mpesa/confirm', function (Request $request) {
    // Save transaction to database
    Payment::create($request->all());

    return response()->json(['ResultCode' => 0]);
});
```

**Confirmation Payload:**

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

### 4. B2C (Business to Customer)

Send money to customers' M-Pesa accounts.

#### Usage

```php
$response = LaravelMpesa::b2c(
    amount: 100,
    phoneNumber: '0712345678',
    commandId: 'BusinessPayment',  // or SalaryPayment, PromotionPayment
    remarks: 'Salary payment',
    occasion: 'Monthly salary'
);
```

#### Response

```php
[
    'ConversationID' => 'AG_20191219_00005797af5d7d75f652',
    'OriginatorConversationID' => '16740-34861180-1',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Accept the service request successfully.'
]
```

#### Callback

**Result URL:**

```php
Route::post('/mpesa/b2c-result', function (Request $request) {
    $result = $request->input('Result');

    if ($result['ResultCode'] == 0) {
        // Money sent successfully
        $params = collect($result['ResultParameters']['ResultParameter'])
            ->pluck('Value', 'Key');

        $receipt = $params['TransactionReceipt'];
        $amount = $params['TransactionAmount'];
    }

    return response()->json(['ResultCode' => 0]);
});
```

**Success Payload:**

```php
[
    'Result' => [
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
    ]
]
```

---

### 5. B2B (Business to Business)

Send money to other businesses.

#### Usage

```php
$response = LaravelMpesa::b2b(
    amount: 5000,
    receiverShortcode: '600000',
    commandId: 'BusinessPayBill',  // or BusinessBuyGoods
    remarks: 'Payment for services',
    accountReference: 'ACC123'
);
```

#### Response

```php
[
    'ConversationID' => 'AG_20191219_00005797af5d7d75f652',
    'OriginatorConversationID' => '16740-34861180-1',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Accept the service request successfully.'
]
```

#### Callback

**Result URL:**

```php
Route::post('/mpesa/b2b-result', function (Request $request) {
    $result = $request->input('Result');

    if ($result['ResultCode'] == 0) {
        // Transfer successful
    }

    return response()->json(['ResultCode' => 0]);
});
```

**Payload:**

```php
[
    'Result' => [
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
    ]
]
```

---

### 6. Transaction Status

Query the status of any transaction.

#### Usage

```php
$response = LaravelMpesa::transactionStatus(
    transactionId: 'OEI2AK4Q16',
    partyA: '600000',
    remarks: 'Status query'
);
```

#### Response

```php
[
    'OriginatorConversationID' => '16740-34861180-1',
    'ConversationID' => 'AG_20191219_00005797af5d7d75f652',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Accept the service request successfully.'
]
```

#### Callback

**Result URL:**

```php
Route::post('/mpesa/transaction-status', function (Request $request) {
    $result = $request->input('Result');
    return response()->json(['ResultCode' => 0]);
});
```

**Payload:**

```php
[
    'Result' => [
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
    ]
]
```

---

### 7. Account Balance

Check your M-Pesa account balance.

#### Usage

```php
$response = LaravelMpesa::accountBalance(
    identifierType: '4',  // 4 = Organization shortcode
    remarks: 'Balance check'
);
```

#### Response

```php
[
    'OriginatorConversationID' => '16740-34861180-1',
    'ConversationID' => 'AG_20191219_00005797af5d7d75f652',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Accept the service request successfully.'
]
```

#### Callback

**Result URL:**

```php
Route::post('/mpesa/account-balance', function (Request $request) {
    $result = $request->input('Result');

    if ($result['ResultCode'] == 0) {
        $balance = $result['ResultParameters']['ResultParameter'][0]['Value'];
        // Parse balance: "Working Account|KES|50000.00|..."
    }

    return response()->json(['ResultCode' => 0]);
});
```

**Payload:**

```php
[
    'Result' => [
        'ResultCode' => 0,
        'ResultParameters' => [
            'ResultParameter' => [
                ['Key' => 'AccountBalance', 'Value' => 'Working Account|KES|50000.00|50000.00|0.00|0.00&Float Account|KES|0.00|...'],
                ['Key' => 'BOCompletedTime', 'Value' => 20191219104550]
            ]
        ]
    ]
]
```

---

### 8. Reversal

Reverse a completed transaction.

#### Usage

```php
$response = LaravelMpesa::reversal(
    transactionId: 'OEI2AK4Q16',
    amount: 100,
    receiverParty: '254712345678',
    remarks: 'Wrong recipient'
);
```

#### Response

```php
[
    'OriginatorConversationID' => '16740-34861180-1',
    'ConversationID' => 'AG_20191219_00005797af5d7d75f652',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Accept the service request successfully.'
]
```

#### Callback

**Result URL:**

```php
Route::post('/mpesa/reversal-result', function (Request $request) {
    $result = $request->input('Result');

    if ($result['ResultCode'] == 0) {
        // Reversal successful
    }

    return response()->json(['ResultCode' => 0]);
});
```

**Success Payload:**

```php
[
    'Result' => [
        'ResultCode' => 0,
        'ResultDesc' => 'The service request is processed successfully.',
        'TransactionID' => 'NLJ41HAY6Q',
        'ResultParameters' => [
            'ResultParameter' => [
                ['Key' => 'Amount', 'Value' => 100],
                ['Key' => 'OriginalTransactionID', 'Value' => 'OEI2AK4Q16'],
                ['Key' => 'TransCompletedTime', 'Value' => 20191219104550],
                ['Key' => 'CreditPartyPublicName', 'Value' => '254712345678 - John Doe']
            ]
        ]
    ]
]
```

**Failed Payload:**

```php
[
    'Result' => [
        'ResultCode' => 2001,
        'ResultDesc' => 'The initiator information is invalid.'
    ]
]
```

---

### 9. Dynamic QR Code

Generate QR codes for payments.

#### Usage

```php
$response = LaravelMpesa::dynamicQr(
    amount: 100,
    refNo: 'INV001',
    trxCode: 'BG',  // BG = Buy Goods, PB = Paybill
    cpi: '174379',  // Your shortcode/till
    size: '300'
);
```

#### Response

```php
[
    'ResponseCode' => 'AG_20191219_000043fdf61864fe9ff5',
    'RequestID' => '16738-27456357-1',
    'ResponseDescription' => 'The service request is processed successfully.',
    'QRCode' => 'iVBORw0KGgoAAAANSUhEUgAAAPoAAAD6AQ...' // Base64 encoded image
]
```

#### Callback

No callback - synchronous response with QR code.

**Display QR Code:**

```php
$qrData = $response['QRCode'];
echo '<img src="data:image/png;base64,' . $qrData . '" />';
```

---

### 10. Pull Transaction

Register for pull transaction queries.

#### Usage

```php
$response = LaravelMpesa::pullTransactionRegister(
    shortcode: '600000',
    requestType: 'Pull',
    nominatedNumber: '254712345678'
);
```

#### Response

```php
[
    'OriginatorConversationID' => '16740-34861180-1',
    'ResponseCode' => '0',
    'ResponseDescription' => 'Accept the service request successfully.'
]
```

#### Callback

No callback for registration. Use the query endpoint to pull transactions.

---

## Multi-Tenant Usage

Switch between accounts dynamically:

```php
// By account ID
LaravelMpesa::forAccount('tenant-123')
    ->stkPush(100, '0712345678');

// By model instance
$account = MpesaAccount::find($id);
LaravelMpesa::withAccount($account)
    ->stkPush(100, '0712345678');

// Override specific settings
LaravelMpesa::forAccount('tenant-123')
    ->withShortcode('600001')
    ->withBuyGoods()
    ->stkPush(100, '0712345678');

// Chain multiple overrides
LaravelMpesa::forAccount('tenant-123')
    ->withBuyGoods()
    ->withShortcode('600001')
    ->withCallbackUrl('https://custom.com/callback')
    ->stkPush(100, '0712345678');
```

## Error Handling

```php
use Exception;

try {
    $response = LaravelMpesa::stkPush(100, '0712345678');
} catch (Exception $e) {
    Log::error('M-Pesa Error: ' . $e->getMessage());
    return back()->with('error', 'Payment failed. Please try again.');
}
```

## Production Checklist

-   [ ] Set `MPESA_ENVIRONMENT=production`
-   [ ] Use production credentials
-   [ ] Enable SSL verification: `MPESA_HTTP_VERIFY=true`
-   [ ] Configure proper callback URLs (HTTPS)
-   [ ] Set up logging: `MPESA_LOG_ENABLED=true`
-   [ ] Test all transactions in sandbox first
-   [ ] Enable HTTP retries (default: 3)
-   [ ] Configure timeouts appropriately
-   [ ] Set up monitoring/alerts
-   [ ] Secure your initiator password

## Testing

Run the package tests:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

## Configuration Options

All configuration options are in `config/mpesa.php`:

```php
return [
    'accounts' => [
        'driver' => 'single',  // or 'database'
        'model' => MpesaAccount::class,
        'cache_ttl' => 300,
    ],

    'http' => [
        'timeout' => 30,
        'connect_timeout' => 10,
        'retries' => 3,
        'verify' => true,
    ],

    'logging' => [
        'enabled' => true,
        'channel' => null,  // Uses default channel
    ],
];
```

## Best Practices

1. **Always validate phone numbers** before sending to M-Pesa
2. **Use unique transaction references** for tracking
3. **Implement idempotency** in callback handlers
4. **Log all transactions** for audit trails
5. **Handle callbacks asynchronously** with queues
6. **Test thoroughly in sandbox** before production
7. **Monitor failed transactions** and set up alerts
8. **Keep credentials secure** (use environment variables)
9. **Implement rate limiting** on payment endpoints
10. **Use database driver for multi-tenant** applications

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on recent changes.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

If you discover a security vulnerability, please email joemuigai004@gmail.com.

## Credits

-   [Joemuigai](https://github.com/joemuigai)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

-   **Issues**: [GitHub Issues](https://github.com/joemuigai/laravel-mpesa/issues)
-   **Documentation**: [GitHub Wiki](https://github.com/joemuigai/laravel-mpesa/wiki)
-   **Email**: joemuigai004@gmail.com

---

Made with ❤️ in Kenya 🇰🇪
