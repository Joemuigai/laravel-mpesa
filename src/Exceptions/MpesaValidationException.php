<?php

namespace Joemuigai\LaravelMpesa\Exceptions;

class MpesaValidationException extends MpesaException
{
    public function __construct(
        string $message,
        public readonly ?string $field = null
    ) {
        parent::__construct($message);
    }

    public static function invalidPhoneNumber(string $phoneNumber): self
    {
        return new self("Invalid phone number format: {$phoneNumber}", 'phoneNumber');
    }

    public static function invalidAmount($amount): self
    {
        return new self("Invalid amount: {$amount}. Amount must be a positive number.", 'amount');
    }

    public static function missingParameter(string $parameter): self
    {
        return new self("Missing required parameter: {$parameter}", $parameter);
    }
}
