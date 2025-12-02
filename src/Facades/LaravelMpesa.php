<?php

namespace Joemuigai\LaravelMpesa\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Laravel M-Pesa Facade
 *
 * @method static \Joemuigai\LaravelMpesa\LaravelMpesa forAccount(string $accountId)
 * @method static \Joemuigai\LaravelMpesa\LaravelMpesa withAccount($account)
 * @method static \Joemuigai\LaravelMpesa\LaravelMpesa withShortcode(string $shortcode)
 * @method static \Joemuigai\LaravelMpesa\LaravelMpesa withPasskey(string $passkey)
 * @method static \Joemuigai\LaravelMpesa\LaravelMpesa withCallbackUrl(string $callbackUrl)
 * @method static \Joemuigai\LaravelMpesa\LaravelMpesa withTransactionType(string $transactionType)
 * @method static \Joemuigai\LaravelMpesa\LaravelMpesa withBuyGoods()
 * @method static \Joemuigai\LaravelMpesa\LaravelMpesa withPaybill()
 *
 * @see \Joemuigai\LaravelMpesa\LaravelMpesa
 */
class LaravelMpesa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Joemuigai\LaravelMpesa\LaravelMpesa::class;
    }
}
