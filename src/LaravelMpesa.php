<?php

namespace Joemuigai\LaravelMpesa;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Joemuigai\LaravelMpesa\Events\B2CInitiated;
use Joemuigai\LaravelMpesa\Events\StkPushInitiated;
use Joemuigai\LaravelMpesa\Exceptions\MpesaApiException;
use Joemuigai\LaravelMpesa\Exceptions\MpesaAuthenticationException;
use Joemuigai\LaravelMpesa\Exceptions\MpesaConfigurationException;
use Joemuigai\LaravelMpesa\Models\MpesaTransaction;
use Joemuigai\LaravelMpesa\Support\ExponentialBackoff;

class LaravelMpesa
{
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
     * Get a configured HTTP client instance with exponential backoff.
     */
    protected function getHttpClient()
    {
        $maxRetries = config('mpesa.http.retries', 3);
        $delays = ExponentialBackoff::getDelays($maxRetries);

        return Http::timeout(config('mpesa.http.timeout', 30))
            ->retry(
                $maxRetries,
                function ($attempt, $exception) use ($delays) {
                    return $delays[$attempt - 1] ?? 100;
                }
            )
            ->baseUrl($this->getBaseUrl());
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
     * Set idempotency key for this request.
     */
    public function withIdempotencyKey(string $key): self
    {
        $instance = clone $this;
        $instance->overrides['idempotency_key'] = $key;

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

        // Priority 2: Default config
        return config("mpesa.{$key}", $default);
    }

    /**
     * Get a valid Access Token from Safaricom.
     *
     * @throws Exception
     */
    /**
     * Get a valid Access Token from Safaricom.
     *
     * @throws Exception
     */
    public function getAccessToken(): string
    {
        $consumerKey = $this->getConfig('credentials.consumer_key');
        $consumerSecret = $this->getConfig('credentials.consumer_secret');

        // Check if we have a valid token in cache
        if (Cache::has('mpesa_access_token')) {
            return Cache::get('mpesa_access_token');
        }

        $response = $this->getHttpClient()
            ->withBasicAuth($consumerKey, $consumerSecret)
            ->get('/oauth/v1/generate?grant_type=client_credentials');

        if ($response->successful()) {
            $data = $response->json();
            $accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'];

            // Cache the token for slightly less than the expiry time to be safe
            Cache::put('mpesa_access_token', $accessToken, $expiresIn - 60);

            return $accessToken;
        }

        throw new MpesaAuthenticationException('Failed to generate access token: '.$response->body());
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

        return $this->sendRequest('post', '/mpesa/stkpush/v1/processrequest', $payload, 'stk_push', [
            'event_class' => StkPushInitiated::class,
        ]);
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

        return $this->sendRequest('post', '/mpesa/c2b/v1/registerurl', $payload, 'c2b_register_url');
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

        return $this->sendRequest('post', '/mpesa/c2b/v1/simulate', $payload, 'c2b_simulate');
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
            throw MpesaConfigurationException::invalidConfig(
                "security.certificates.{$env}",
                "Certificate file not found at: {$certPath}"
            );
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

        return $this->sendRequest('post', '/mpesa/b2c/v3/paymentrequest', $payload, 'b2c_payment', [
            'event_class' => B2CInitiated::class,
        ]);
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

        return $this->sendRequest('post', '/mpesa/transactionstatus/v1/query', $payload, 'transaction_status_query');
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

        return $this->sendRequest('post', '/mpesa/accountbalance/v1/query', $payload, 'account_balance_query');
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

        return $this->sendRequest('post', '/mpesa/reversal/v1/request', $payload, 'reversal_request');
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

        return $this->sendRequest('post', '/mpesa/qrcode/v1/generate', $payload, 'dynamic_qr');
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

        return $this->sendRequest('post', '/mpesa/pulltransactions/v1/register', $payload, 'pull_transaction_register');
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

        return $this->sendRequest('post', '/mpesa/b2b/v1/paymentrequest', $payload, 'b2b_payment');
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

        return $this->sendRequest('post', '/mpesa/stkpushquery/v1/query', $payload, 'stk_push_query');
    }

    /**
     * Send request to M-Pesa API with automatic logging.
     *
     * @throws Exception
     */
    protected function sendRequest(string $method, string $endpoint, array $payload, string $transactionType, array $metadata = []): array
    {
        // Check for idempotency
        $idempotencyKey = $this->overrides['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existing = MpesaTransaction::where('idempotency_key', $idempotencyKey)
                ->where('status', 'SUCCESS')
                ->first();

            if ($existing && $existing->response_payload) {
                return $existing->response_payload;
            }
        }

        $token = $this->getAccessToken();

        // Create pending transaction log
        $transaction = null;
        if (config('mpesa.logging.enabled', true)) {
            try {
                $transaction = MpesaTransaction::create(array_merge([
                    'transaction_type' => $transactionType,
                    'idempotency_key' => $idempotencyKey,
                    'status' => 'PENDING',
                    'request_payload' => $payload,
                    'merchant_request_id' => null,
                    'checkout_request_id' => $payload['CheckoutRequestID'] ?? null,
                    'party_a' => $payload['PartyA'] ?? null,
                    'party_b' => $payload['PartyB'] ?? null,
                    'amount' => $payload['Amount'] ?? null,
                    'account_reference' => $payload['AccountReference'] ?? ($payload['BillRefNumber'] ?? null),
                    'transaction_desc' => $payload['TransactionDesc'] ?? ($payload['Remarks'] ?? null),
                    'remarks' => $payload['Remarks'] ?? null,
                ], $metadata));
            } catch (Exception $e) {
                // Silently fail logging to not disrupt the transaction
            }
        }

        // Send request
        $response = $this->getHttpClient()
            ->withToken($token)
            ->$method($endpoint, $payload);

        $responseData = $response->json();

        // Update transaction log
        if ($transaction) {
            try {
                $status = $response->successful() ? 'SUCCESS' : 'FAILED';

                $updateData = [
                    'status' => $status,
                    'response_payload' => $responseData,
                    'result_code' => $responseData['ResponseCode'] ?? ($responseData['ResultCode'] ?? null),
                    'result_desc' => $responseData['ResponseDescription'] ?? ($responseData['ResultDesc'] ?? ($responseData['errorMessage'] ?? null)),
                    'merchant_request_id' => $responseData['MerchantRequestID'] ?? null,
                    'checkout_request_id' => $responseData['CheckoutRequestID'] ?? null,
                    'conversation_id' => $responseData['ConversationID'] ?? null,
                    'originator_conversation_id' => $responseData['OriginatorConversationID'] ?? null,
                ];

                $transaction->update($updateData);

                // Dispatch event if specified
                if ($transaction && isset($metadata['event_class'])) {
                    $eventClass = $metadata['event_class'];
                    event(new $eventClass($transaction));
                }
            } catch (Exception $e) {
                // Silently fail logging
            }
        }

        if ($response->successful()) {
            return $responseData;
        }

        throw MpesaApiException::fromResponse($transactionType, $responseData);
    }
}
