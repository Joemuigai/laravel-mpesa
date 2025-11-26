<?php

namespace Joemuigai\LaravelMpesa\Models;

use Illuminate\Database\Eloquent\Model;

class MpesaCallback extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mpesa_callbacks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'callback_type',
        'merchant_request_id',
        'checkout_request_id',
        'conversation_id',
        'originator_conversation_id',
        'payload',
        'processed',
        'processed_at',
        'ip_address',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
        'processed_at' => 'datetime',
    ];

    /**
     * Scope a query to only include unprocessed callbacks.
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    /**
     * Scope a query to filter by callback type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('callback_type', $type);
    }

    /**
     * Mark the callback as processed.
     */
    public function markAsProcessed(): bool
    {
        $this->processed = true;
        $this->processed_at = now();

        return $this->save();
    }

    /**
     * Get transaction details from the callback payload.
     *
     * @return array<string, mixed>
     */
    public function getTransactionDetails(): array
    {
        $payload = $this->payload;

        return match ($this->callback_type) {
            'stk_push' => $this->extractStkPushDetails($payload),
            'c2b' => $this->extractC2BDetails($payload),
            'b2c' => $this->extractB2CDetails($payload),
            'b2b' => $this->extractB2BDetails($payload),
            default => $payload,
        };
    }

    /**
     * Extract STK Push transaction details.
     */
    protected function extractStkPushDetails(array $payload): array
    {
        $body = $payload['Body']['stkCallback'] ?? [];

        return [
            'merchant_request_id' => $body['MerchantRequestID'] ?? null,
            'checkout_request_id' => $body['CheckoutRequestID'] ?? null,
            'result_code' => $body['ResultCode'] ?? null,
            'result_desc' => $body['ResultDesc'] ?? null,
            'metadata' => $body['CallbackMetadata']['Item'] ?? [],
        ];
    }

    /**
     * Extract C2B transaction details.
     */
    protected function extractC2BDetails(array $payload): array
    {
        return [
            'transaction_type' => $payload['TransactionType'] ?? null,
            'trans_id' => $payload['TransID'] ?? null,
            'trans_time' => $payload['TransTime'] ?? null,
            'trans_amount' => $payload['TransAmount'] ?? null,
            'business_short_code' => $payload['BusinessShortCode'] ?? null,
            'bill_ref_number' => $payload['BillRefNumber'] ?? null,
            'invoice_number' => $payload['InvoiceNumber'] ?? null,
            'msisdn' => $payload['MSISDN'] ?? null,
            'first_name' => $payload['FirstName'] ?? null,
            'middle_name' => $payload['MiddleName'] ?? null,
            'last_name' => $payload['LastName'] ?? null,
        ];
    }

    /**
     * Extract B2C transaction details.
     */
    protected function extractB2CDetails(array $payload): array
    {
        $result = $payload['Result'] ?? [];

        return [
            'conversation_id' => $result['ConversationID'] ?? null,
            'originator_conversation_id' => $result['OriginatorConversationID'] ?? null,
            'result_code' => $result['ResultCode'] ?? null,
            'result_desc' => $result['ResultDesc'] ?? null,
            'transaction_id' => $result['TransactionID'] ?? null,
        ];
    }

    /**
     * Extract B2B transaction details.
     */
    protected function extractB2BDetails(array $payload): array
    {
        $result = $payload['Result'] ?? [];

        return [
            'conversation_id' => $result['ConversationID'] ?? null,
            'originator_conversation_id' => $result['OriginatorConversationID'] ?? null,
            'result_code' => $result['ResultCode'] ?? null,
            'result_desc' => $result['ResultDesc'] ?? null,
            'transaction_id' => $result['TransactionID'] ?? null,
        ];
    }
}
