<?php

namespace Joemuigai\LaravelMpesa\Support;

class WebhookVerifier
{
    /**
     * Verify M-Pesa webhook signature.
     *
     * Note: M-Pesa currently doesn't provide webhook signatures,
     * but this helper is ready for when they do.
     *
     * @param  array  $payload  The webhook payload
     * @param  string  $signature  The signature from headers
     * @param  string  $secret  The webhook secr et
     * @return bool True if signature is valid
     */
    public static function verify(array $payload, string $signature, string $secret): bool
    {
        return RequestSigner::verify($payload, $signature, $secret);
    }

    /**
     * Verify webhook came from allowed IP addresses.
     *
     * @param  string  $clientIp  The client IP address
     * @param  array  $allowedIps  Array of allowed IP addresses
     * @return bool True if IP is allowed
     */
    public static function verifyIp(string $clientIp, array $allowedIps): bool
    {
        return in_array($clientIp, $allowedIps, true);
    }
}
