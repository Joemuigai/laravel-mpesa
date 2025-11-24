<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Example M-Pesa Account Model for Multi-Tenant Scenarios
 *
 * This model stores M-Pesa credentials per account/tenant.
 * The 'credentials' JSON column stores the same structure as config/mpesa.php
 */
class MpesaAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'tenant_id',
        'credentials',
        'is_active',
        'environment',
    ];

    protected $casts = [
        'credentials' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Example credentials structure stored in the 'credentials' JSON column:
     *
     * {
     *     "credentials": {
     *         "consumer_key": "your_consumer_key",
     *         "consumer_secret": "your_consumer_secret"
     *     },
     *     "stk": {
     *         "shortcode": "174379",
     *         "passkey": "your_passkey",
     *         "callback_url": "https://yourdomain.com/mpesa/callback"
     *     },
     *     "c2b": {
     *         "shortcode": "600000",
     *         "validation_url": "https://yourdomain.com/mpesa/validate",
     *         "confirmation_url": "https://yourdomain.com/mpesa/confirm"
     *     },
     *     "b2c": {
     *         "shortcode": "600000",
     *         "result_url": "https://yourdomain.com/mpesa/b2c/result",
     *         "timeout_url": "https://yourdomain.com/mpesa/b2c/timeout",
     *         "command_id": "BusinessPayment"
     *     },
     *     "initiator": {
     *         "name": "testapi",
     *         "password": "your_initiator_password"
     *     },
     *     "callbacks": {
     *         "balance": {
     *             "result": "https://yourdomain.com/mpesa/balance/result",
     *             "timeout": "https://yourdomain.com/mpesa/balance/timeout"
     *         },
     *         "reversal": {
     *             "result": "https://yourdomain.com/mpesa/reversal/result",
     *             "timeout": "https://yourdomain.com/mpesa/reversal/timeout"
     *         },
     *         "b2b": {
     *             "result": "https://yourdomain.com/mpesa/b2b/result",
     *             "timeout": "https://yourdomain.com/mpesa/b2b/timeout"
     *         }
     *     }
     * }
     */

    /**
     * Scope to get active accounts only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Get the active M-Pesa account for a tenant
     */
    public static function getActiveForTenant($tenantId)
    {
        return static::active()
            ->forTenant($tenantId)
            ->first();
    }
}
