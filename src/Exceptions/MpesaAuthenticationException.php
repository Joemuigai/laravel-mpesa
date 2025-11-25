<?php

namespace Joemuigai\LaravelMpesa\Exceptions;

class MpesaAuthenticationException extends MpesaException
{
    public function __construct(string $message = 'Failed to authenticate with M-Pesa API')
    {
        parent::__construct($message);
    }
}
