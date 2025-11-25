<?php

namespace Joemuigai\LaravelMpesa\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class MpesaEvent
{
    use Dispatchable, SerializesModels;
}
