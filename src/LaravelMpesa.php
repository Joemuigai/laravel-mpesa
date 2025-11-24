<?php

namespace Joemuigai\LaravelMpesa;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LaravelMpesa
{
    /**
     * Account ID for multi-tenant scenarios.
     */
    protected ?string $accountId = null;

    /**
     * Runtime configuration overrides.
     */
    protected array $overrides = [];

    /**
     * Get the base URL based on the environment.
     */
    protected function getBaseUrl(): string
    {
        $env = config('mpesa.environment', 'sandbox');

        return config("mpesa.base_urls.{$env}");
    }

    /**
     * Get a configured HTTP client instance.
     */
    protected function getHttpClient()
    {
        return Http::timeout(config('mpesa.http.timeout', 30))
            ->retry(config('mpesa.http.retries', 3), 100)
            ->baseUrl($this->getBaseUrl());
    }

    /**
     * Set account context for multi-tenant scenarios.
     */
    public function forAccount(string $accountId): self
    {
        $instance = clone $this;
        $instance->accountId = $accountId;

        return $instance;
    }

    /**
     * Set account using model instance.
     *
     * @param  mixed  $account  Model with 'id' property or identifier
     */
    public function withAccount($account): self
    {
        $instance = clone $this;
        $instance->accountId = is_object($account) && isset($account->id) ? $account->id : (string) $account;

        return $instance;
    }

    /**
     * Override shortcode for this request.
     */
    public function withShortcode(string $shortcode): self
    {
        $instance = clone $this;
        $instance->overrides['shortcode'] = $shortcode;

        return $instance;
    }

    /**
     * Override callback URL for this request.
     */
    public function withCallbackUrl(string $callbackUrl): self
    {
        $instance = clone $this;
        $instance->overrides['callback_url'] = $callbackUrl;

        return $instance;
    }

    /**
     * Override transaction type for this request.
     *
     * @param  string  $transactionType  (e.g., 'CustomerPayBillOnline' or 'CustomerBuyGoodsOnline')
     */
    public function withTransactionType(string $transactionType): self
    {
        $instance = clone $this;
        $instance->overrides['transaction_type'] = $transactionType;

        return $instance;
    }

    /**
     * Use Buy Goods (Till Number) for this request.
     */
    public function withBuyGoods(): self
    {
        $instance = clone $this;
        $instance->overrides['transaction_type'] = 'CustomerBuyGoodsOnline';

        return $instance;
    }

    /**
     * Use Paybill for this request.
     */
    public function withPaybill(): self
    {
        $instance = clone $this;
        $instance->overrides['transaction_type'] = 'CustomerPayBillOnline';

        return $instance;
    }

    /**
     * Resolve configuration value based on context.
     *
     * Priority:
     * 1. Runtime overrides
     * 2. Account-specific config (database driver)
     * 3. Default config
     *
     * @param  string  $key  Dot notation key (e.g., 'stk.shortcode')
     * @param  mixed  $default
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        // Priority 1: Check runtime overrides (flat key, last segment)
        $lastSegment = substr($key, strrpos($key, '.') + 1);
        if (isset($this->overrides[$lastSegment])) {
            return $this->overrides[$lastSegment];
        }

        // Priority 2: Account-specific config (database driver)
        if ($this->accountId && config('mpesa.accounts.driver') === 'database') {
            return $this->getAccountConfig($this->accountId, $key, $default);
        }

        // Priority 3: Default config
        return config("mpesa.{$key}", $default);
    }

    /**
     * Load account-specific configuration from database.
     *
     * @param  mixed  $default
     * @return mixed
     *
     * @throws Exception
     */
    protected function getAccountConfig(string $accountId, string $key, $default = null)
    {
        $cacheKey = "mpesa.account.{$accountId}.{$key}";
        $cacheTtl = config('mpesa.accounts.cache_ttl', 300);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($accountId, $key, $default) {
            $modelClass = config('mpesa.accounts.model');

            if (! $modelClass || ! class_exists($modelClass)) {
                throw new Exception("M-Pesa account model not configured or does not exist: {$modelClass}");
            }

            $account = $modelClass::find($accountId);

            if (! $account) {
                throw new Exception("M-Pesa account not found: {$accountId}");
            }

            // Access nested config via dot notation from 'credentials' JSON column
            return data_get($account->credentials, $key, $default);
        });
    }

    /**
     * Get a valid Access Token from Safaricom.
     *
     * @throws Exception
     */
    public function getAccessToken(): string
    {
        // Different cache key per account for multi-tenant scenarios
        $cacheKey = $this->accountId
            ? "mpesa_access_token_{$this->accountId}"
            : 'mpesa_access_token';

        // Check if we have a valid token in cache
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $consumerKey = $this->getConfig('credentials.consumer_key');
        $consumerSecret = $this->getConfig('credentials.consumer_secret');

        $response = $this->getHttpClient()
            ->withBasicAuth($consumerKey, $consumerSecret)
            ->get('/oauth/v1/generate?grant_type=client_credentials');

        if ($response->successful()) {
            $data = $response->json();
            $accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'];

            // Cache the token for slightly less than the expiry time to be safe
            Cache::put($cacheKey, $accessToken, $expiresIn - 60);

            return $accessToken;
        }

        throw new Exception('Failed to generate access token: '.$response->body());
    }

    /**
     * Generate the password for STK Push.
     */
    protected function generatePassword(string $shortcode, string $passkey, string $timestamp): string
    {
        return base64_encode($shortcode.$passkey.$timestamp);
    }

    /**
     * Initiate an STK Push (Lipa Na M-Pesa Online).
     *
     * @param  int|float  $amount
     * @param  string|null  $transactionType  'paybill' or 'buy_goods' (defaults to config)
     *
     * @throws Exception
     */
    public function stkPush($amount, string $phoneNumber, ?string $reference = null, ?string $description = null, ?string $transactionType = null): array
    {
        $token = $this->getAccessToken();

        $shortcode = $this->getConfig('stk.shortcode');
        $passkey = $this->getConfig('stk.passkey');
        $timestamp = now()->format('YmdHis');
        $password = $this->generatePassword($shortcode, $passkey, $timestamp);
        $callbackUrl = $this->getConfig('stk.callback_url');

        $phoneNumber = $this->formatPhoneNumber($phoneNumber);

        // Determine transaction type with precedence:
        // 1. Method parameter override
        // 2. Runtime override (withBuyGoods/withPaybill/withTransactionType)
        // 3. Config default
        $transactionTypeKey = $transactionType ?? (
            isset($this->overrides['transaction_type'])
            ? null  // Already resolved via override
            : $this->getConfig('stk.default_type', 'paybill')
        );

        if ($transactionTypeKey && in_array($transactionTypeKey, ['paybill', 'buy_goods'])) {
            $finalTransactionType = $this->getConfig("stk.transaction_types.{$transactionTypeKey}");
        } elseif (isset($this->overrides['transaction_type'])) {
            $finalTransactionType = $this->overrides['transaction_type'];
        } else {
            $finalTransactionType = $this->getConfig('stk.transaction_types.paybill', 'CustomerPayBillOnline');
        }

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => $finalTransactionType,
            'Amount' => (int) $amount,
            'PartyA' => $phoneNumber,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $reference ?? $this->getConfig('stk.defaults.account_reference', 'Payment'),
            'TransactionDesc' => $description ?? $this->getConfig('stk.defaults.transaction_desc', 'Payment'),
        ];

        $response = $this->getHttpClient()
            ->withToken($token)
            ->post('/mpesa/stkpush/v1/processrequest', $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('STK Push failed: '.$response->body());
    }

    /**
     * Format phone number to 2547XXXXXXXX or 2541XXXXXXXX.
     */
    protected function formatPhoneNumber(string $number): string
    {
        // Remove non-numeric characters
        $number = preg_replace('/\D/', '', $number);

        // If starts with 0, replace with 254
        if (str_starts_with($number, '0')) {
            return '254'.substr($number, 1);
        }

        // If starts with 7 or 1 (and is 9 digits), prepend 254
        if ((str_starts_with($number, '7') || str_starts_with($number, '1')) && strlen($number) === 9) {
            return '254'.$number;
        }

        // If starts with 254, return as is
        if (str_starts_with($number, '254')) {
            return $number;
        }

        return $number;
    }

    /**
     * Register C2B Confirmation and Validation URLs.
     *
     * @throws Exception
     */
    public function c2bRegisterUrl(): array
    {
        $token = $this->getAccessToken();

        $payload = [
            'ShortCode' => $this->getConfig('c2b.shortcode'),
            'ResponseType' => $this->getConfig('c2b.response_type', 'Completed'),
            'ConfirmationURL' => $this->getConfig('c2b.confirmation_url'),
            'ValidationURL' => $this->getConfig('c2b.validation_url'),
        ];

        $response = $this->getHttpClient()
            ->withToken($token)
            ->post('/mpesa/c2b/v1/registerurl', $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('C2B Register URL failed: '.$response->body());
    }

    /**
     * Simulate a C2B Transaction (Sandbox Only).
     *
     * @param  int|float  $amount
     *
     * @throws Exception
     */
    public function c2bSimulate($amount, string $phoneNumber, string $commandId = 'CustomerPayBillOnline', ?string $accountNumber = null): array
    {
        $token = $this->getAccessToken();

        $shortcode = $this->getConfig('c2b.shortcode');
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);

        $payload = [
            'ShortCode' => $shortcode,
            'CommandID' => $commandId,
            'Amount' => (int) $amount,
            'Msisdn' => $phoneNumber,
            'BillRefNumber' => $accountNumber ?? 'Test',
        ];

        $response = $this->getHttpClient()
            ->withToken($token)
            ->post('/mpesa/c2b/v1/simulate', $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('C2B Simulate failed: '.$response->body());
    }

    /**
     * Generate Security Credential for B2C/B2B.
     *
     * @throws Exception
     */
    public function generateSecurityCredential(string $password): string
    {
        $env = $this->getConfig('environment', 'sandbox');
        $certPath = $this->getConfig("security.certificates.{$env}");

        if (! file_exists($certPath)) {
            throw new Exception("Certificate file not found at: {$certPath}");
        }

        $pubKey = file_get_contents($certPath);
        openssl_public_encrypt($password, $encrypted, $pubKey, OPENSSL_PKCS1_PADDING);

        return base64_encode($encrypted);
    }

    /**
     * Initiate a B2C Payment.
     *
     * @param  int|float  $amount
     *
     * @throws Exception
     */
    public function b2c($amount, string $phoneNumber, ?string $commandId = null, ?string $remarks = null, ?string $occasion = null): array
    {
        $token = $this->getAccessToken();

        $initiatorName = $this->getConfig('initiator.name');
        $initiatorPassword = $this->getConfig('initiator.password');
        $securityCredential = $this->generateSecurityCredential($initiatorPassword);

        $shortcode = $this->getConfig('b2c.shortcode');
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);

        $payload = [
            'OriginatorConversationID' => Str::uuid()->toString(),
            'InitiatorName' => $initiatorName,
            'SecurityCredential' => $securityCredential,
            'CommandID' => $commandId ?? $this->getConfig('b2c.command_id', 'BusinessPayment'),
            'Amount' => (int) $amount,
            'PartyA' => $shortcode,
            'PartyB' => $phoneNumber,
            'Remarks' => $remarks ?? $this->getConfig('b2c.defaults.remarks', 'B2C Payment'),
            'QueueTimeOutURL' => $this->getConfig('b2c.timeout_url'),
            'ResultURL' => $this->getConfig('b2c.result_url'),
            'Occasion' => $occasion ?? $this->getConfig('b2c.defaults.occasion'),
        ];

        $response = $this->getHttpClient()
            ->withToken($token)
            ->post('/mpesa/b2c/v3/paymentrequest', $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('B2C Payment failed: '.$response->body());
    }

    /**
     * Check Transaction Status.
     *
     * @throws Exception
     */
    public function transactionStatus(string $transactionId, ?string $originalConversationId = null, string $identifierType = '4', ?string $remarks = null, ?string $occasion = null): array
    {
        $token = $this->getAccessToken();

        $initiatorName = $this->getConfig('initiator.name');
        $initiatorPassword = $this->getConfig('initiator.password');
        $securityCredential = $this->generateSecurityCredential($initiatorPassword);

        $partyA = $this->getConfig('transaction_status.party_a');

        $payload = [
            'Initiator' => $initiatorName,
            'SecurityCredential' => $securityCredential,
            'CommandID' => 'TransactionStatusQuery',
            'TransactionID' => $transactionId,
            'OriginalConversationID' => $originalConversationId,
            'PartyA' => $partyA,
            'IdentifierType' => $identifierType,
            'ResultURL' => $this->getConfig('transaction_status.result_url'),
            'QueueTimeOutURL' => $this->getConfig('transaction_status.timeout_url'),
            'Remarks' => $remarks ?? $this->getConfig('transaction_status.defaults.remarks', 'Transaction Status Query'),
            'Occasion' => $occasion ?? $this->getConfig('transaction_status.defaults.occasion'),
        ];

        $response = $this->getHttpClient()
            ->withToken($token)
            ->post('/mpesa/transactionstatus/v1/query', $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('Transaction Status Query failed: '.$response->body());
    }

    /**
     * Check Account Balance.
     *
     * @throws Exception
     */
    public function accountBalance(string $identifierType = '4', ?string $remarks = null): array
    {
        $token = $this->getAccessToken();

        $initiatorName = $this->getConfig('initiator.name');
        $initiatorPassword = $this->getConfig('initiator.password');
        $securityCredential = $this->generateSecurityCredential($initiatorPassword);

        $partyA = $this->getConfig('transaction_status.party_a'); // Usually same as paybill

        $payload = [
            'Initiator' => $initiatorName,
            'SecurityCredential' => $securityCredential,
            'CommandID' => 'AccountBalance',
            'PartyA' => $partyA,
            'IdentifierType' => $identifierType,
            'Remarks' => $remarks ?? 'Account Balance Query',
            'QueueTimeOutURL' => $this->getConfig('callbacks.balance.timeout'),
            'ResultURL' => $this->getConfig('callbacks.balance.result'),
        ];

        $response = $this->getHttpClient()
            ->withToken($token)
            ->post('/mpesa/accountbalance/v1/query', $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('Account Balance Query failed: '.$response->body());
    }

    /**
     * Reverse a Transaction.
     *
     * @param  int|float  $amount
     * @param  string|null  $receiverParty
     *
     * @throws Exception
     */
    public function reversal(string $transactionId, $amount, string $receiverParty, string $receiverIdentifierType = '11', ?string $remarks = null, ?string $occasion = null): array
    {
        $token = $this->getAccessToken();

        $initiatorName = $this->getConfig('initiator.name');
        $initiatorPassword = $this->getConfig('initiator.password');
        $securityCredential = $this->generateSecurityCredential($initiatorPassword);

        // ReceiverParty is usually the Shortcode for C2B reversals
        $receiverParty = $receiverParty ?? $this->getConfig('c2b.shortcode');

        $payload = [
            'Initiator' => $initiatorName,
            'SecurityCredential' => $securityCredential,
            'CommandID' => 'TransactionReversal',
            'TransactionID' => $transactionId,
            'Amount' => (int) $amount,
            'ReceiverParty' => $receiverParty,
            'RecieverIdentifierType' => $receiverIdentifierType,
            'ResultURL' => $this->getConfig('callbacks.reversal.result'),
            'QueueTimeOutURL' => $this->getConfig('callbacks.reversal.timeout'),
            'Remarks' => $remarks ?? 'Reversal',
            'Occasion' => $occasion,
        ];

        $response = $this->getHttpClient()
            ->withToken($token)
            ->post('/mpesa/reversal/v1/request', $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('Reversal failed: '.$response->body());
    }

    /**
     * Generate Dynamic QR Code.
     *
     * @param  int|float  $amount
     * @param  string  $trxCode  Transaction Type (PB: Paybill, BG: Buy Goods, WA: Withdraw Cash)
     * @param  string  $cpi  Credit Party Identifier (Shortcode/Till Number)
     *
     * @throws Exception
     */
    public function dynamicQr($amount, string $refNo, string $trxCode, string $cpi, ?string $size = '300'): array
    {
        $token = $this->getAccessToken();

        $payload = [
            'MerchantName' => $this->getConfig('initiator.name'),
            'RefNo' => $refNo,
            'Amount' => (int) $amount,
            'TrxCode' => $trxCode,
            'CPI' => $cpi,
            'Size' => $size,
        ];

        $response = $this->getHttpClient()
            ->withToken($token)
            ->post('/mpesa/qrcode/v1/generate', $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('Dynamic QR Code generation failed: '.$response->body());
    }

    /**
     * Register Pull Transaction Callbacks.
     *
     * @throws Exception
     */
    public function pullTransactionRegister(): array
    {
        $token = $this->getAccessToken();

        $payload = [
            'ShortCode' => $this->getConfig('pull.shortcode'),
            'RequestType' => $this->getConfig('pull.register.request_type', 'Pull'),
            'NominatedNumber' => $this->getConfig('pull.register.nominated_number'),
            'CallBackURL' => $this->getConfig('pull.register.callback_url'),
        ];

        $response = $this->getHttpClient()
            ->withToken($token)
            ->post('/mpesa/pulltransactions/v1/register', $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('Pull Transaction Register failed: '.$response->body());
    }

    /**
     * Business to Business (B2B) Payment.
     *
     * @param  int|float  $amount
     *
     * @throws Exception
     */
    public function b2b($amount, string $receiverShortcode, ?string $commandId = null, ?string $remarks = null, ?string $accountReference = null): array
    {
        $token = $this->getAccessToken();

        $initiatorName = $this->getConfig('initiator.name');
        $initiatorPassword = $this->getConfig('initiator.password');
        $securityCredential = $this->generateSecurityCredential($initiatorPassword);

        $senderShortcode = $this->getConfig('b2c.shortcode'); // Usually same as B2C/Paybill

        $payload = [
            'Initiator' => $initiatorName,
            'SecurityCredential' => $securityCredential,
            'CommandID' => $commandId ?? 'BusinessPayBill',
            'SenderIdentifierType' => '4',
            'RecieverIdentifierType' => '4',
            'Amount' => (int) $amount,
            'PartyA' => $senderShortcode,
            'PartyB' => $receiverShortcode,
            'AccountReference' => $accountReference ?? 'B2B Payment',
            'Remarks' => $remarks ?? 'B2B Payment',
            'QueueTimeOutURL' => $this->getConfig('callbacks.b2b.timeout'),
            'ResultURL' => $this->getConfig('callbacks.b2b.result'),
        ];

        $response = $this->getHttpClient()
            ->withToken($token)
            ->post('/mpesa/b2b/v1/paymentrequest', $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('B2B Payment failed: '.$response->body());
    }

    /**
     * Query STK Push Status (M-Pesa Express Query).
     *
     * @throws Exception
     */
    public function stkPushQuery(string $checkoutRequestId): array
    {
        $token = $this->getAccessToken();

        $shortcode = $this->getConfig('stk.shortcode');
        $passkey = $this->getConfig('stk.passkey');
        $timestamp = now()->format('YmdHis');
        $password = $this->generatePassword($shortcode, $passkey, $timestamp);

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        $response = $this->getHttpClient()
            ->withToken($token)
            ->post('/mpesa/stkpushquery/v1/query', $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('STK Push Query failed: '.$response->body());
    }
}
