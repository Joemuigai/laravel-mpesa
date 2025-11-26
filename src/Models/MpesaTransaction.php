<?php

namespace Joemuigai\LaravelMpesa\Models;

use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mpesa_transactions';

    /**
     * @var array<string, mixed>|null
     */
    protected $response_payload;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transaction_type',
        'idempotency_key',
        'merchant_request_id',
        'checkout_request_id',
        'conversation_id',
        'originator_conversation_id',
        'transaction_id',
        'party_a',
        'party_b',
        'amount',
        'account_reference',
        'transaction_desc',
        'remarks',
        'status',
        'result_code',
        'result_desc',
        'request_payload',
        'response_payload',
        'callback_payload',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'callback_payload' => 'array',
    ];

    /**
     * Scope a query to only include successful transactions.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'SUCCESS');
    }

    /**
     * Scope a query to only include failed transactions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'FAILED');
    }

    /**
     * Scope a query to only include pending transactions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }
}
