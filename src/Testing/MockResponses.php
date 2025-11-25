<?php

namespace Joemuigai\LaravelMpesa\Testing;

class MockResponses
{
    /**
     * Get a successful STK Push response.
     */
    public static function stkPushSuccess(
        string $merchantRequestId = 'mock-merchant-123',
        string $checkoutRequestId = 'mock-checkout-456'
    ): array {
        return [
            'MerchantRequestID' => $merchantRequestId,
            'CheckoutRequestID' => $checkoutRequestId,
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
            'CustomerMessage' => 'Success. Request accepted for processing',
        ];
    }

    /**
     * Get a failed STK Push response.
     */
    public static function stkPushFailed(string $errorMessage = 'Insufficient Balance'): array
    {
        return [
            'RequestID' => 'mock-request-789',
            'errorCode' => '400.002.02',
            'errorMessage' => $errorMessage,
        ];
    }

    /**
     * Get a successful STK Push callback.
     */
    public static function stkPushCallback(
        string $checkoutRequestId = 'mock-checkout-456',
        int $resultCode = 0,
        string $mpesaReceiptNumber = 'MOCK-RECEIPT-123'
    ): array {
        return [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'mock-merchant-123',
                    'CheckoutRequestID' => $checkoutRequestId,
                    'ResultCode' => $resultCode,
                    'ResultDesc' => $resultCode === 0 ? 'The service request is processed successfully.' : 'Request cancelled by user',
                    'CallbackMetadata' => $resultCode === 0 ? [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 100],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => $mpesaReceiptNumber],
                            ['Name' => 'Balance'],
                            ['Name' => 'TransactionDate', 'Value' => 20241125193000],
                            ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                        ],
                    ] : null,
                ],
            ],
        ];
    }

    /**
     * Get a successful B2C response.
     */
    public static function b2cSuccess(
        string $conversationId = 'mock-conv-123',
        string $originatorConversationId = 'mock-orig-456'
    ): array {
        return [
            'ConversationID' => $conversationId,
            'OriginatorConversationID' => $originatorConversationId,
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ];
    }

    /**
     * Get a successful B2C callback.
     */
    public static function b2cCallback(
        string $conversationId = 'mock-conv-123',
        int $resultCode = 0
    ): array {
        return [
            'Result' => [
                'ResultType' => 0,
                'ResultCode' => $resultCode,
                'ResultDesc' => $resultCode === 0 ? 'The service request is processed successfully.' : 'The service request failed',
                'OriginatorConversationID' => 'mock-orig-456',
                'ConversationID' => $conversationId,
                'TransactionID' => 'MOCK-TXN-789',
                'ResultParameters' => $resultCode === 0 ? [
                    'ResultParameter' => [
                        ['Key' => 'TransactionAmount', 'Value' => 100],
                        ['Key' => 'TransactionReceipt', 'Value' => 'MOCK-RECEIPT-B2C'],
                        ['Key' => 'B2CRecipientIsRegisteredCustomer', 'Value' => 'Y'],
                        ['Key' => 'B2CChargesPaidAccountAvailableFunds', 'Value' => 1000],
                        ['Key' => 'ReceiverPartyPublicName', 'Value' => '254712345678 - John Doe'],
                        ['Key' => 'TransactionCompletedDateTime', 'Value' => '25.11.2024 19:30:00'],
                        ['Key' => 'B2CUtilityAccountAvailableFunds', 'Value' => 50000],
                        ['Key' => 'B2CWorkingAccountAvailableFunds', 'Value' => 100000],
                    ],
                ] : null,
            ],
        ];
    }

    /**
     * Get a successful C2B callback.
     */
    public static function c2bCallback(
        string $transId = 'MOCK-C2B-123',
        float $amount = 100.00
    ): array {
        return [
            'TransactionType' => 'Pay Bill',
            'TransID' => $transId,
            'TransTime' => '20241125193000',
            'TransAmount' => $amount,
            'BusinessShortCode' => '600000',
            'BillRefNumber' => 'ACC-001',
            'InvoiceNumber' => '',
            'OrgAccountBalance' => '50000.00',
            'ThirdPartyTransID' => '',
            'MSISDN' => '254712345678',
            'FirstName' => 'John',
            'MiddleName' => '',
            'LastName' => 'Doe',
        ];
    }

    /**
     * Get an access token response.
     */
    public static function accessToken(string $token = 'mock-access-token', int $expiresIn = 3599): array
    {
        return [
            'access_token' => $token,
            'expires_in' => $expiresIn,
        ];
    }
}
