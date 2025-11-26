<?php

namespace Joemuigai\LaravelMpesa\Services;

use InvalidArgumentException;

class CallbackParser
{
    /**
     * Parse and normalize a callback payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function parse(string $callbackType, array $payload): array
    {
        return match ($callbackType) {
            'stk_push' => $this->parseStkPush($payload),
            'c2b_confirmation', 'c2b_validation' => $this->parseC2B($payload),
            'b2c_result', 'b2c_timeout' => $this->parseB2C($payload),
            'b2b_result', 'b2b_timeout' => $this->parseB2B($payload),
            'transaction_status_result', 'transaction_status_timeout' => $this->parseTransactionStatus($payload),
            'account_balance_result', 'account_balance_timeout' => $this->parseAccountBalance($payload),
            'reversal_result', 'reversal_timeout' => $this->parseReversal($payload),
            default => throw new InvalidArgumentException("Unknown callback type: {$callbackType}"),
        };
    }

    /**
     * Parse STK Push callback.
     */
    protected function parseStkPush(array $payload): array
    {
        $stkCallback = $payload['Body']['stkCallback'] ?? [];

        $normalizedData = [
            'merchant_request_id' => $stkCallback['MerchantRequestID'] ?? null,
            'checkout_request_id' => $stkCallback['CheckoutRequestID'] ?? null,
            'result_code' => $stkCallback['ResultCode'] ?? null,
            'result_desc' => $stkCallback['ResultDesc'] ?? null,
        ];

        // Extract metadata if available (successful transactions)
        if (isset($stkCallback['CallbackMetadata']['Item'])) {
            $items = $stkCallback['CallbackMetadata']['Item'];
            $metadata = [];

            foreach ($items as $item) {
                $name = $item['Name'] ?? null;
                $value = $item['Value'] ?? null;

                if ($name) {
                    $metadata[$name] = $value;
                }
            }

            $normalizedData['amount'] = $metadata['Amount'] ?? null;
            $normalizedData['mpesa_receipt_number'] = $metadata['MpesaReceiptNumber'] ?? null;
            $normalizedData['balance'] = $metadata['Balance'] ?? null;
            $normalizedData['transaction_date'] = $metadata['TransactionDate'] ?? null;
            $normalizedData['phone_number'] = $metadata['PhoneNumber'] ?? null;
        }

        return $normalizedData;
    }

    /**
     * Parse C2B callback.
     */
    protected function parseC2B(array $payload): array
    {
        return [
            'transaction_type' => $payload['TransactionType'] ?? null,
            'trans_id' => $payload['TransID'] ?? null,
            'trans_time' => $payload['TransTime'] ?? null,
            'trans_amount' => $payload['TransAmount'] ?? null,
            'business_short_code' => $payload['BusinessShortCode'] ?? null,
            'bill_ref_number' => $payload['BillRefNumber'] ?? null,
            'invoice_number' => $payload['InvoiceNumber'] ?? null,
            'org_account_balance' => $payload['OrgAccountBalance'] ?? null,
            'third_party_trans_id' => $payload['ThirdPartyTransID'] ?? null,
            'msisdn' => $payload['MSISDN'] ?? null,
            'first_name' => $payload['FirstName'] ?? null,
            'middle_name' => $payload['MiddleName'] ?? null,
            'last_name' => $payload['LastName'] ?? null,
        ];
    }

    /**
     * Parse B2C callback.
     */
    protected function parseB2C(array $payload): array
    {
        $result = $payload['Result'] ?? [];
        $resultParameters = $result['ResultParameters']['ResultParameter'] ?? [];

        $normalized = [
            'conversation_id' => $result['ConversationID'] ?? null,
            'originator_conversation_id' => $result['OriginatorConversationID'] ?? null,
            'result_code' => $result['ResultCode'] ?? null,
            'result_desc' => $result['ResultDesc'] ?? null,
            'transaction_id' => $result['TransactionID'] ?? null,
        ];

        // Parse result parameters
        foreach ($resultParameters as $param) {
            $key = $param['Key'] ?? null;
            $value = $param['Value'] ?? null;

            if ($key) {
                $normalized[strtolower(str_replace([' ', '-'], '_', $key))] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Parse B2B callback.
     */
    protected function parseB2B(array $payload): array
    {
        return $this->parseB2C($payload); // Same structure as B2C
    }

    /**
     * Parse Transaction Status callback.
     */
    protected function parseTransactionStatus(array $payload): array
    {
        return $this->parseB2C($payload); // Same structure as B2C/B2B
    }

    /**
     * Parse Account Balance callback.
     */
    protected function parseAccountBalance(array $payload): array
    {
        return $this->parseB2C($payload); // Same structure
    }

    /**
     * Parse Reversal callback.
     */
    protected function parseReversal(array $payload): array
    {
        return $this->parseB2C($payload); // Same structure
    }
}
