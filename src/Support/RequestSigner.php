<?php

namespace Joemuigai\LaravelMpesa\Support;

class RequestSigner
{
    /**
     * Sign a request payload using HMAC-SHA256.
     *
     * @param  array  $payload  The request payload to sign
     * @param  string  $secret  The secret key for signing
     * @return string The HMAC signature
     */
    public static function sign(array $payload, string $secret): string
    {
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Verify a request signature.
     *
     * @param  array  $payload  The request payload
     * @param  string  $signature  The signature to verify
     * @param  string  $secret  The secret key
     * @return bool True if signature is valid
     */
    public static function verify(array $payload, string $signature, string $secret): bool
    {
        $expectedSignature = self::sign($payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
