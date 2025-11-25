<?php

namespace Joemuigai\LaravelMpesa\Events;

class CallbackProcessed extends MpesaEvent
{
    public function __construct(
        public string $callbackType,
        public array $payload,
        public mixed $result = null
    ) {}
}
