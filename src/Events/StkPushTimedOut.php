<?php

namespace Joemuigai\LaravelMpesa\Events;

use Joemuigai\LaravelMpesa\Models\MpesaTransaction;

class StkPushTimedOut extends MpesaEvent
{
    public function __construct(
        public MpesaTransaction $transaction
    ) {}
}
