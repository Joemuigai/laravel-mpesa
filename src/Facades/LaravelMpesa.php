<?php

namespace Joemuigai\LaravelMpesa\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Laravel M-Pesa Facade
 *
 * @method static string getAccessToken()
 * @method static array stkPush($amount, string $phoneNumber, ?string $reference = null, ?string $description = null, ?string $transactionType = null)
 * @method static array stkPushQuery(string $checkoutRequestId)
 * @method static array c2bRegisterUrl(string $validationUrl, string $confirmationUrl, ?string $responseType = 'Completed')
 * @method static array c2bSimulate($amount, string $phoneNumber, ?string $billRefNumber = null)
 * @method static array b2c($amount, string $phoneNumber, string $commandId = 'BusinessPayment', ?string $remarks = null, ?string $occasion = null)
 * @method static array b2b($amount, string $receiverParty, string $receiverIdentifierType = '4', string $accountReference = '', ?string $remarks = null)
 * @method static array transactionStatus(string $transactionId, string $partyA, ?string $remarks = null, ?string $occasion = null)
 * @method static array accountBalance(string $partyA, string $identifierType = '4', ?string $remarks = null)
 * @method static array reversal(string $transactionId, $amount, string $receiverParty, ?string $remarks = null, ?string $occasion = null)
 * @method static array dynamicQr(string $merchantName, string $refNo, $amount, string $trxCode = 'BG', string $cpi = '174379', ?string $size = '300')
 * @method static array pullTransactionRegister(string $shortcode, ?string $requestType = 'Pull', ?string $nominatedNumber = null, ?string $callbackUrl = null)
 * @method static \Joemuigai\LaravelMpesa\LaravelMpesa forAccount(string $accountId)
 * @method static \Joemuigai\LaravelMpesa\LaravelMpesa withAccount($account)
 * @method static \Joemuigai\LaravelMpesa\LaravelMpesa withShortcode(string $shortcode)
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
