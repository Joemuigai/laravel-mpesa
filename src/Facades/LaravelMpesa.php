<?php

namespace Joemuigai\LaravelMpesa\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Joemuigai\LaravelMpesa\LaravelMpesa
 */
class LaravelMpesa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Joemuigai\LaravelMpesa\LaravelMpesa::class;
    }
}
