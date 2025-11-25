<?php

namespace Joemuigai\LaravelMpesa\Events;

use Joemuigai\LaravelMpesa\Models\MpesaTransaction;

class B2CCompleted extends MpesaEvent
{
    public function __construct(
        public MpesaTransaction $transaction,
        public array $callbackData
    ) {}
}
