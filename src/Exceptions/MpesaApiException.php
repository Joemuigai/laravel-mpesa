<?php

namespace Joemuigai\LaravelMpesa\Exceptions;

class MpesaApiException extends MpesaException
{
    public function __construct(
        string $message,
        public readonly ?string $resultCode = null,
        public readonly ?string $resultDesc = null,
        public readonly ?array $responseData = null
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(string $transactionType, array $responseData): self
    {
        $resultCode = $responseData['ResponseCode'] ?? ($responseData['ResultCode'] ?? 'UNKNOWN');
        $resultDesc = $responseData['ResponseDescription'] ?? ($responseData['ResultDesc'] ?? ($responseData['errorMessage'] ?? 'Unknown error'));

        $message = ucfirst($transactionType)." failed: {$resultDesc} (Code: {$resultCode})";

        return new self($message, $resultCode, $resultDesc, $responseData);
    }
}
