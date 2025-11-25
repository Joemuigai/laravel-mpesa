<?php

namespace Joemuigai\LaravelMpesa\Events;

use Joemuigai\LaravelMpesa\Models\MpesaTransaction;

class B2CInitiated extends MpesaEvent
{
    public function __construct(
        public MpesaTransaction $transaction
    ) {}
}
