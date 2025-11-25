<?php

namespace Joemuigai\LaravelMpesa\Events;

class CallbackReceived extends MpesaEvent
{
    public function __construct(
        public string $callbackType,
        public array $payload
    ) {}
}
