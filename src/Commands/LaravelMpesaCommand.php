<?php

namespace Joemuigai\LaravelMpesa\Commands;

use Illuminate\Console\Command;

class LaravelMpesaCommand extends Command
{
    public $signature = 'laravel-mpesa';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
