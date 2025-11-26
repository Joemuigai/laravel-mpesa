<?php

namespace Joemuigai\LaravelMpesa\Events;

use Joemuigai\LaravelMpesa\Models\MpesaTransaction;

class StkPushFailed extends MpesaEvent
{
    public function __construct(
        public MpesaTransaction $transaction,
        public $error
    ) {}
}
