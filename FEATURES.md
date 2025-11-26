# Laravel M-Pesa Package Features

A comprehensive guide to all features and capabilities of the Laravel M-Pesa package for integrating Safaricom's Daraja API into your Laravel application.

---

## Table of Contents

-   [M-Pesa API Integration](#m-pesa-api-integration)
-   [Transaction Management](#transaction-management)
-   [Event System](#event-system)
-   [Security Features](#security-features)
-   [Testing & Development Tools](#testing--development-tools)
-   [Multi-Tenancy Support](#multi-tenancy-support)
-   [Developer Experience](#developer-experience)

---

## M-Pesa API Integration

### 1. STK Push (Lipa Na M-Pesa Online)

Prompt customers to enter their M-Pesa PIN to complete payment.

**Features**:

-   Automatic phone number formatting (supports `07xx`, `254xx`, `+254xx` formats)
-   Support for both Paybill and Buy Goods (Till Number)
-   Transaction type selection (runtime or config-based)
-   STK Push status query
-   Custom reference and description
-   Automatic password generation
-   Builder pattern for flexible configuration

**Methods**:

```php
->stkPush($amount, $phoneNumber, $reference, $description, $transactionType)
->stkPushQuery($checkoutRequestId)
->withPaybill()  // Force Paybill transaction type
->withBuyGoods() // Force Buy Goods transaction type
```

**Events Dispatched**:

-   `StkPushInitiated`
-   `StkPushCompleted`
-   `StkPushFailed`
-   `StkPushTimedOut`

---

### 2. Customer to Business (C2B)

Receive payments when customers pay to your Paybill or Till Number.

**Features**:

-   URL registration for validation and confirmation callbacks
-   Transaction simulation (sandbox only)
-   Custom validation logic
-   Automatic callback processing
-   Support for both Paybill and Till Number

**Methods**:

```php
->c2bRegisterUrl()
->c2bSimulate($amount, $phoneNumber, $commandId, $accountNumber)
```

**Events Dispatched**:

-   `C2BReceived`
-   `CallbackReceived`
-   `CallbackProcessed`

---

### 3. Business to Customer (B2C)

Send money from your business account to customer phone numbers.

**Features**:

-   Multiple command IDs (Salary, Promotion, Business Payment)
-   Configurable default remarks and occasion
-   Automatic security credential generation
-   OriginatorConversationID for tracking
-   Automatic phone number formatting

**Methods**:

```php
->b2c($amount, $phoneNumber, $commandId, $remarks, $occasion)
```

**Supported Command IDs**:

-   `SalaryPayment` - Employee salaries
-   `BusinessPayment` - General business payments
-   `PromotionPayment` - Promotional payments

**Events Dispatched**:

-   `B2CInitiated`
-   `B2CCompleted`
-   `B2CFailed`

---

### 4. Business to Business (B2B)

Transfer funds between business accounts.

**Features**:

-   Paybill to Paybill transfers
-   Paybill to Buy Goods transfers
-   Account reference tracking
-   Automatic security credentials
-   Configurable command IDs

**Methods**:

```php
->b2b($amount, $receiverShortcode, $commandId, $remarks, $accountReference)
```

**Supported Command IDs**:

-   `BusinessPayBill` (default)
-   `BusinessBuyGoods`
-   `DisburseFundsToBusiness`
-   `BusinessToBusinessTransfer`
-   `MerchantToMerchantTransfer`

---

### 5. Transaction Status Query

Check the status of any M-Pesa transaction.

**Features**:

-   Query by transaction ID
-   Support for OriginatorConversationID
-   Configurable identifier types
-   Custom remarks and occasion

**Methods**:

```php
->transactionStatus($transactionId, $originalConversationId, $identifierType, $remarks, $occasion)
```

**Identifier Types**:

-   `1` - MSISDN
-   `2` - Till Number
-   `4` - Organization shortcode (default)

---

### 6. Account Balance Query

Check your M-Pesa account balance.

**Features**:

-   Real-time balance checks
-   Multiple identifier type support
-   Result and timeout URLs
-   Custom remarks

**Methods**:

```php
->accountBalance($identifierType, $remarks)
```

---

### 7. Transaction Reversal

Reverse erroneous M-Pesa transactions.

**Features**:

-   Full or partial reversals
-   Automatic security credentials
-   Transaction ID validation
-   Receiver party specification
-   Custom remarks and occasion

**Methods**:

```php
->reversal($transactionId, $amount, $receiverParty, $receiverIdentifierType, $remarks, $occasion)
```

**Receiver Identifier Types**:

-   `11` - MSISDN (default)
-   `4` - Organization shortcode

---

### 8. Dynamic QR Code Generation

Generate QR codes for customer payments.

**Features**:

-   Support for Paybill, Buy Goods, and Agent Till
-   Configurable QR code size
-   Reference number tracking
-   Merchant name customization

**Methods**:

```php
->dynamicQr($amount, $refNo, $trxCode, $cpi, $size)
```

**Transaction Codes**:

-   `PB` - Paybill
-   `BG` - Buy Goods (Till)
-   `WA` - Withdraw Cash (Agent)
-   `SM` - Send Money

---

### 9. Pull Transaction Query

Query transactions from customer accounts (requires additional permissions).

**Features**:

-   URL registration
-   Transaction pulling
-   Nominated MSISDN configuration

**Methods**:

```php
->pullTransactionRegister()
```

---

## Transaction Management

### Database Models

**MpesaTransaction Model**

-   Stores all transaction details
-   Request/response/callback payload logging
-   Status tracking (PENDING, SUCCESS, FAILED)
-   Idempotency key support
-   Automatic timestamps

**Features**:

```php
// Scopes
MpesaTransaction::successful()->get()
MpesaTransaction::failed()->get()
MpesaTransaction::pending()->get()

// Attributes
$transaction->amount
$transaction->party_a
$transaction->party_b
$transaction->status
$transaction->result_code
$transaction->result_desc
$transaction->request_payload
$transaction->response_payload
$transaction->callback_payload
```

---

**MpesaCallback Model**

-   Stores all callback payloads
-   Processing status tracking
-   IP address logging
-   Type-specific extraction methods

**Features**:

```php
// Scopes
MpesaCallback::unprocessed()->get()
MpesaCallback::byType('stk_push')->get()

// Methods
$callback->markAsProcessed()
$callback->getTransactionDetails() // Extracts relevant data

// Properties
$callback->callback_type
$callback->payload
$callback->processed
$callback->processed_at
```

---

### Automatic Transaction Logging

All API requests are automatically logged to the database:

-   Request payload
-   Response data
-   Idempotency keys
-   Transaction metadata
-   Timestamps

---

## Event System

### Real-Time Transaction Events

Listen to transaction lifecycle events for custom business logic.

**STK Push Events**:

```php
Event::listen(StkPushInitiated::class, function($event) {
    // $event->transaction
});

Event::listen(StkPushCompleted::class, function($event) {
    // $event->transaction
    // $event->callbackData
});

Event::listen(StkPushFailed::class, function($event) {
    // $event->transaction
    // $event->error
});

Event::listen(StkPushTimedOut::class, function($event) {
    // $event->transaction
});
```

**B2C Events**:

```php
Event::listen(B2CInitiated::class, function($event) {
    // $event->transaction
});

Event::listen(B2CCompleted::class, function($event) {
    // $event->transaction
    // $event->callbackData
});

Event::listen(B2CFailed::class, function($event) {
    // $event->transaction
    // $event->error
});
```

**Callback Events**:

```php
Event::listen(C2BReceived::class, function($event) {
    // $event->payload
});

Event::listen(CallbackReceived::class, function($event) {
    // $event->payload
});

Event::listen(CallbackProcessed::class, function($event) {
    // $event->transaction
});
```

---

## Security Features

### 1. Encrypted Credentials

Store sensitive API credentials encrypted in the database.

**Cast Usage**:

```php
use Joemuigai\LaravelMpesa\Casts\EncryptedCredential;

protected $casts = [
    'initiator_password' => EncryptedCredential::class,
    'api_key' => EncryptedCredential::class,
];
```

---

### 2. Request Signing

HMAC-SHA256 request signing for additional security.

**Usage**:

```php
use Joemuigai\LaravelMpesa\Support\RequestSigner;

$signature = RequestSigner::sign($payload, $secret);
$isValid = RequestSigner::verify($payload, $signature, $secret);
```

---

### 3. Webhook Verification

Verify M-Pesa webhook authenticity.

**Usage**:

```php
use Joemuigai\LaravelMpesa\Support\WebhookVerifier;

// Verify signature
$isValid = WebhookVerifier::verifySignature($payload, $signature, $secret);

// Verify IP address
$isTrusted = WebhookVerifier::verifyIpAddress($ipAddress);
```

---

### 4. Security Credentials

Automatic encryption of initiator passwords using M-Pesa certificates.

**Features**:

-   Separate sandbox and production certificates
-   Automatic certificate selection based on environment
-   Cached credentials for performance

---

### 5. Callback Middleware

Protect callback routes with verification middleware.

**Usage**:

```php
Route::post('/mpesa/callback', [MpesaCallbackController::class, 'handle'])
    ->middleware('verify.mpesa.callback');
```

---

## Testing & Development Tools

### 1. Mock Responses

Pre-built mock responses for all API endpoints.

**Usage**:

```php
use Joemuigai\LaravelMpesa\Testing\MockResponses;

// STK Push mocks
Http::fake([
    '*' => MockResponses::stkPushSuccess(),
]);

Http::fake([
    '*' => MockResponses::stkPushPending(),
]);

// B2C mocks
Http::fake([
    '*' => MockResponses::b2cSuccess(),
]);

// Error mocks
Http::fake([
    '*' => MockResponses::insufficientBalance(),
]);
```

**Available Mock Methods**:

-   `stkPushSuccess()`, `stkPushPending()`, `stkPushFailed()`
-   `b2cSuccess()`, `b2cFailed()`
-   `c2bSuccess()`
-   `accessTokenSuccess()`, `accessTokenFailed()`
-   `insufficientBalance()`, `invalidPhoneNumber()`
-   And more...

---

### 2. Callback Simulation

Artisan command to simulate M-Pesa callbacks locally.

**Usage**:

```bash
# Simulate STK Push callback
php artisan mpesa:simulate-callback stk_push --status=success

# Simulate B2C callback
php artisan mpesa:simulate-callback b2c --status=failed

# Simulate C2B callback
php artisan mpesa:simulate-callback c2b
```

**Options**:

-   `--status`: success, failed, timeout
-   `--amount`: Transaction amount
-   `--phone`: Customer phone number
-   `--reference`: Account reference

---

## Multi-Tenancy Support

### Flexible Credential Management

**Single Account Mode**:

```php
// Credentials in .env or config
Mpesa::stkPush($amount, $phone);
```

**Multi-Tenant Mode**:

```php
// Runtime credential override
Mpesa::withShortcode($tenant->mpesa_shortcode)
    ->withCallbackUrl($tenant->callback_url)
    ->stkPush($amount, $phone);
```

**Hybrid Mode**:

```php
// Mix config and runtime overrides
Mpesa::withShortcode($branch->shortcode)
    ->stkPush($amount, $phone);
```

---

## Developer Experience

### 1. Interactive Installation

```bash
php artisan mpesa:install
```

**Features**:

-   Scenario selection (Single/Multi-tenant/Hybrid)
-   API selection (choose only what you need)
-   Transaction type preference
-   Automatic config generation
-   Environment variable setup
-   Callback publishing

---

### 2. Builder Pattern

Fluent API for runtime configuration.

```php
Mpesa::withShortcode('174379')
    ->withCallbackUrl('https://example.com/callback')
    ->withTransactionType('CustomerPayBillOnline')
    ->withIdempotencyKey('unique-key-123')
    ->stkPush(100, '0712345678');
```

**Available Builders**:

-   `withShortcode($shortcode)`
-   `withCallbackUrl($url)`
-   `withTransactionType($type)`
-   `withIdempotencyKey($key)`
-   `withPaybill()` - Shorthand for Paybill type
-   `withBuyGoods()` - Shorthand for Buy Goods type

---

### 3. Idempotency Support

Prevent duplicate transactions with idempotency keys.

```php
Mpesa::withIdempotencyKey('order-'.$orderId)
    ->stkPush($amount, $phone);

// Subsequent calls with same key return cached response
```

---

### 4. Phone Number Formatting

Automatic formatting of Kenyan phone numbers.

**Supported Formats**:

-   `0712345678` → `254712345678`
-   `254712345678` → `254712345678`
-   `+254712345678` → `254712345678`
-   `712345678` → `254712345678`
-   `0112345678` → `254112345678` (Safaricom new prefix)

---

### 5. Error Handling

Specific exception classes for different error types.

```php
use Joemuigai\LaravelMpesa\Exceptions\{
    MpesaApiException,
    MpesaAuthenticationException,
    MpesaValidationException,
    MpesaConfigurationException
};

try {
    Mpesa::stkPush($amount, $phone);
} catch (MpesaApiException $e) {
    // M-Pesa API error
    $e->getResultCode();
    $e->getResultDesc();
    $e->getResponseData();
} catch (MpesaAuthenticationException $e) {
    // Token generation failed
} catch (MpesaValidationException $e) {
    // Invalid parameters
} catch (MpesaConfigurationException $e) {
    // Missing/invalid config
}
```

---

### 6. Exponential Backoff

Automatic retry with exponential backoff for failed requests.

**Features**:

-   Progressive delay between retries
-   Random jitter to prevent thundering herd
-   Configurable retry count
-   Automatic on HTTP failures

---

### 7. Publishable Assets

Publish only what you need:

```bash
# Publish everything
php artisan vendor:publish --provider="Joemuigai\LaravelMpesa\LaravelMpesaServiceProvider"

# Publish specific assets
php artisan vendor:publish --tag=mpesa-config
php artisan vendor:publish --tag=mpesa-migrations
php artisan vendor:publish --tag=mpesa-events
php artisan vendor:publish --tag=mpesa-stubs
```

---

### 8. Callback Parser Service

Parse different callback types with type-safe data extraction.

```php
use Joemuigai\LaravelMpesa\Services\CallbackParser;

$parser = new CallbackParser($request->all());

if ($parser->isValidStkPush()) {
    $data = $parser->parseStkPush();
    // $data['result_code'], $data['amount'], etc.
}

if ($parser->isValidB2C()) {
    $data = $parser->parseB2C();
}

if ($parser->isValidC2B()) {
    $data = $parser->parseC2B();
}
```

---

## Configuration Options

Comprehensive configuration via `config/mpesa.php`:

-   **Environment**: Sandbox or Production
-   **Credentials**: Consumer Key, Consumer Secret
-   **Initiator**: Name and Password for B2C/B2B
-   **STK Push**: Shortcode, Passkey, Callback URL, Transaction Type
-   **C2B**: Shortcode, URLs, Response Type
-   **B2C**: Shortcode, URLs, Defaults (Remarks, Occasion, Command ID)
-   **B2B**: URLs
-   **Transaction Status**: Party A, URLs
-   **Account Balance**: URLs
-   **Reversal**: URLs
-   **Pull Transaction**: Configuration
-   **Security**: Certificate paths, Cache TTL
-   **HTTP**: Timeout, Retries, Verify SSL
-   **Logging**: Transaction logging, Callback logging
-   **Callbacks**: Result and Timeout URLs for all APIs

---

## Performance Features

-   **Token Caching**: Access tokens cached with auto-refresh
-   **Database Indexing**: Optimized queries on transactions
-   **Lazy Loading**: Relationships loaded on-demand
-   **Response Caching**: Idempotency prevents duplicate API calls
-   **Exponential Backoff**: Efficient retry mechanism

---

## Summary

The Laravel M-Pesa package provides:

✅ **11 M-Pesa APIs** fully integrated  
✅ **Complete Event System** for real-time notifications  
✅ **Automatic Transaction Logging** with full audit trail  
✅ **Multi-Tenancy Support** for SaaS applications  
✅ **Security Features** (encryption, signing, verification)  
✅ **Testing Tools** (mocks, simulators)  
✅ **Developer Tools** (builder pattern, auto-formatting)  
✅ **Production Ready** (error handling, retries, logging)  
✅ **Flexible Configuration** (single/multi-tenant/hybrid)  
✅ **Complete Documentation** and type hints

---

## Getting Started

1. Install: `composer require joemuigai/laravel-mpesa`
2. Setup: `php artisan mpesa:install`
3. Configure: Update `.env` with your credentials
4. Use: `Mpesa::stkPush($amount, $phone)`

For detailed usage examples, see [USAGE.md](USAGE.md).

For API error codes, see [RESULT_CODES.md](RESULT_CODES.md).
