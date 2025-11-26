<?php

namespace Joemuigai\LaravelMpesa\Exceptions;

class MpesaConfigurationException extends MpesaException
{
    public function __construct(
        string $message,
        public readonly ?string $configKey = null
    ) {
        parent::__construct($message);
    }

    public static function missingConfig(string $configKey): self
    {
        return new self("Missing required configuration: mpesa.{$configKey}", $configKey);
    }

    public static function invalidConfig(string $configKey, string $reason): self
    {
        return new self("Invalid configuration for mpesa.{$configKey}: {$reason}", $configKey);
    }
}
