<?php

namespace Joemuigai\LaravelMpesa\Events;

use Joemuigai\LaravelMpesa\Models\MpesaTransaction;

class StkPushCompleted extends MpesaEvent
{
    public function __construct(
        public MpesaTransaction $transaction,
        public array $callbackData
    ) {}
}
