<?php

namespace Joemuigai\LaravelMpesa\Events;

class C2BReceived extends MpesaEvent
{
    public function __construct(
        public array $payload
    ) {}
}
