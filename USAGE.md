# Usage Guide for Laravel M-Pesa

This document provides detailed usage examples, expected responses, callbacks, and payloads for each of the supported M-Pesa APIs.

> [!TIP] > **Production Tip**: Always wrap your M-Pesa API calls in `try-catch` blocks to handle connection errors or API exceptions gracefully.

## Table of Contents

1. [STK Push](#1-stk-push-lipa-na-m-pesa-online)
2. [STK Push Query](#2-stk-push-query)
3. [C2B (Customer to Business)](#3-c2b-customer-to-business)
4. [B2C (Business to Customer)](#4-b2c-business-to-customer)
5. [B2B (Business to Business)](#5-b2b-business-to-business)
6. [Transaction Status](#6-transaction-status)
7. [Account Balance](#7-account-balance)
8. [Reversal](#8-reversal)
9. [Dynamic QR Code](#9-dynamic-qr-code)
10. [Pull Transaction](#10-pull-transaction)
11. [Multi-Tenant Usage](#11-multi-tenant-usage)

---

## Prerequisites

```php
use Joemuigai\LaravelMpesa\Facades\LaravelMpesa;
use Illuminate\Support\Facades\Log;
```

---

## 1. STK Push (Lipa Na M-Pesa Online)

Initiate a payment prompt on the customer's phone.

### Usage

```php
try {
    $response = LaravelMpesa::stkPush(
        amount: 1500,
        phoneNumber: '254712345678', // Format: 2547XXXXXXXX
        reference: 'INV-1001',
        description: 'Invoice payment'
    );

    // Save merchant_request_id and checkout_request_id to database
    $merchantRequestId = $response['MerchantRequestID'];
    $checkoutRequestId = $response['CheckoutRequestID'];

} catch (\Exception $e) {
    Log::error("STK Push Failed: " . $e->getMessage());
    // Handle error (e.g., notify user)
}
```

### With Paybill (default)

```php
$response = LaravelMpesa::withPaybill()
    ->stkPush(1500, '254712345678', 'INV-1001');
```

### With Till (Buy Goods)

```php
$response = LaravelMpesa::withBuyGoods()
    ->stkPush(1500, '254712345678');
```

### With Specific Till/PartyB (Hybrid Scenarios)

For multi-tenant or multi-store platforms where different tills receive their own funds:

```php
// Using facade - Store 1's till
$response = LaravelMpesa::withBuyGoods()
    ->withShortcode('174379')     // BusinessShortCode
    ->withPartyB('174379')         // Till receiving the funds
    ->withPasskey('store1_passkey')
    ->stkPush(1500, '254712345678', 'INV-1001');

// Using MpesaService - Store 2's till
$response = $mpesaService->withBuyGoods()
    ->withShortcode('600001')
    ->withPartyB('600001')
    ->withPasskey('store2_passkey')
    ->stkPush(1500, '254712345678', 'INV-1002');
```

Or configure via environment variables:

```env
MPESA_STK_PARTY_B=174379
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

Define this route in `routes/web.php` or `routes/api.php` and ensure it matches `MPESA_STK_CALLBACK_URL` in your `.env`.

```php
use Illuminate\Http\Request;

Route::post('/mpesa/stk-callback', function (Request $request) {
    $data = $request->all();

    Log::info('STK Callback:', $data);

    if (isset($data['Body']['stkCallback'])) {
        $callback = $data['Body']['stkCallback'];

        if ($callback['ResultCode'] == 0) {
            // Payment successful
            $items = $callback['CallbackMetadata']['Item'];

            // Helper to extract values safely
            $getItem = fn($name) => collect($items)->firstWhere('Name', $name)['Value'] ?? null;

            $amount = $getItem('Amount');
            $mpesaReceipt = $getItem('MpesaReceiptNumber');
            $transactionDate = $getItem('TransactionDate');
            $phoneNumber = $getItem('PhoneNumber');

            // TODO: Update your database using CheckoutRequestID
        } else {
            // Payment failed/cancelled
            $reason = $callback['ResultDesc'];
        }
    }

    return response()->json(['ResultCode' => 0]);
});
```

---

## 2. STK Push Query

Check the status of an STK Push if the callback is delayed or missing.

### Usage

```php
try {
    $status = LaravelMpesa::stkPushQuery(
        checkoutRequestId: 'ws_CO_191220191020363925'
    );

    if ($status['ResultCode'] == '0') {
        // Transaction was successful
    }
} catch (\Exception $e) {
    Log::error("STK Query Failed: " . $e->getMessage());
}
```

---

## 3. C2B (Customer to Business)

### Register URLs (One-time setup)

You only need to run this once (e.g., via a command or seeder) to tell M-Pesa where to send callbacks.

```php
try {
    $response = LaravelMpesa::c2bRegisterUrl(
        validationUrl: 'https://yourdomain.com/api/mpesa/validate',
        confirmationUrl: 'https://yourdomain.com/api/mpesa/confirm'
    );
} catch (\Exception $e) {
    // Handle error
}
```

### Simulate Transaction (Sandbox Only)

```php
try {
    $response = LaravelMpesa::c2bSimulate(
        amount: 100,
        phoneNumber: '254712345678',
        billRefNumber: 'ACCOUNT123'
    );
} catch (\Exception $e) {
    // Handle error
}
```

### Validation Callback

M-Pesa asks you: "Should I accept this payment?"

```php
Route::post('/mpesa/validate', function (Request $request) {
    // Logic to validate account number, amount, etc.
    $isValid = true;

    if ($isValid) {
        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted'
        ]);
    } else {
        return response()->json([
            'ResultCode' => 1,
            'ResultDesc' => 'Rejected'
        ]);
    }
});
```

### Confirmation Callback

M-Pesa tells you: "Payment completed."

```php
Route::post('/mpesa/confirm', function (Request $request) {
    // Save transaction to database
    // $request->input('TransID'), $request->input('TransAmount'), etc.

    return response()->json(['ResultCode' => 0]);
});
```

---

## 4. B2C (Business to Customer)

Send money to a customer (e.g., refunds, salaries, winnings).

### Usage

```php
try {
    $response = LaravelMpesa::b2c(
        amount: 100,
        phoneNumber: '254712345678',
        commandId: 'BusinessPayment', // Options: SalaryPayment, BusinessPayment, PromotionPayment
        remarks: 'Refund',
        occasion: 'Holiday'
    );

    // Store ConversationID and OriginatorConversationID
} catch (\Exception $e) {
    Log::error("B2C Failed: " . $e->getMessage());
}
```

### Callback

Configure `MPESA_B2C_RESULT_URL` and `MPESA_B2C_TIMEOUT_URL` in `.env`.

```php
Route::post('/mpesa/b2c-result', function (Request $request) {
    $result = $request->input('Result');

    if ($result['ResultCode'] == 0) {
        // Success
        $params = collect($result['ResultParameters']['ResultParameter'])->pluck('Value', 'Key');
        $receipt = $params['TransactionReceipt'];
    }

    return response()->json(['ResultCode' => 0]);
});
```

---

## 5. B2B (Business to Business)

Transfer funds to another business Paybill or Till.

### Usage

```php
try {
    $response = LaravelMpesa::b2b(
        amount: 5000,
        receiverShortcode: '600123',
        commandId: 'BusinessPayBill', // or BusinessBuyGoods
        remarks: 'Supplier Payment',
        accountReference: 'INV-500'
    );
} catch (\Exception $e) {
    Log::error("B2B Failed: " . $e->getMessage());
}
```

---

## 6. Transaction Status

Check the status of ANY transaction (C2B, B2C, etc.) using its ID.

### Usage

```php
try {
    $response = LaravelMpesa::transactionStatus(
        transactionId: 'OEI2AK4Q16', // The M-Pesa receipt number
        partyA: '600000', // Your shortcode
        remarks: 'Verify payment'
    );
} catch (\Exception $e) {
    // Handle error
}
```

---

## 7. Account Balance

Check your Paybill/Till balance.

### Usage

```php
try {
    $response = LaravelMpesa::accountBalance(
        identifierType: '4', // 4 for Shortcode
        remarks: 'End of day check'
    );
} catch (\Exception $e) {
    // Handle error
}
```

---

## 8. Reversal

Reverse a transaction (requires special permissions from Safaricom).

### Usage

```php
try {
    $response = LaravelMpesa::reversal(
        transactionId: 'OEI2AK4Q16',
        amount: 100,
        receiverParty: '600000', // Your shortcode
        remarks: 'Erroneous payment'
    );
} catch (\Exception $e) {
    // Handle error
}
```

---

## 9. Dynamic QR Code

Generate a QR code for customers to scan and pay.

### Usage

```php
try {
    $qr = LaravelMpesa::dynamicQr(
        amount: 2500,
        refNo: 'ORDER-123',
        trxCode: 'PB', // PB = Paybill, BG = Buy Goods
        cpi: '600000', // Your shortcode
        size: '300' // Size in pixels
    );

    $qrImage = $qr['QRCode']; // Base64 string

} catch (\Exception $e) {
    // Handle error
}
```

---

## 10. Pull Transaction

Register to "pull" missed transactions.

### Usage

```php
try {
    $response = LaravelMpesa::pullTransactionRegister(
        shortcode: '600000',
        requestType: 'Pull',
        nominatedNumber: '254712345678'
    );
} catch (\Exception $e) {
    // Handle error
}
```

---

## 11. Multi-Tenant Usage

For SaaS applications where you manage multiple merchants.

### Database Setup

Ensure you have run the migrations:

```bash
php artisan migrate
```

### Creating an Account

```php
use App\Models\MpesaAccount;

$account = MpesaAccount::create([
    'name' => 'Merchant A',
    'credentials' => [
        'consumer_key' => '...',
        'consumer_secret' => '...',
        'stk' => [
            'shortcode' => '123456',
            'passkey' => '...',
            'callback_url' => 'https://app.com/callback/123456'
        ]
    ]
]);
```

### Using an Account

```php
// Option 1: Pass the model instance
LaravelMpesa::withAccount($account)
    ->stkPush(100, '254712345678');

// Option 2: Pass the tenant ID (if using custom logic)
LaravelMpesa::forAccount('merchant-123')
    ->stkPush(100, '254712345678');
```

---

_Documentation updated for **Laravel M-Pesa v0.2.0**_
