<?php

namespace Joemuigai\LaravelMpesa\Services;

use Joemuigai\LaravelMpesa\Exceptions\MpesaConfigurationException;

class MpesaSecurityService
{
    /**
     * Certificate cache time-to-live in seconds.
     */
    private const CACHE_TTL = 3600;

    /**
     * Token expiry buffer in seconds (reduce TTL to avoid edge cases).
     */
    public const TOKEN_EXPIRY_BUFFER_SECONDS = 60;

    /**
     * Generate password for STK Push.
     *
     * @param  string  $shortcode  Business shortcode
     * @param  string  $passkey  Lipa Na M-Pesa passkey
     * @param  string  $timestamp  Timestamp in YmdHis format
     * @return string Base64 encoded password
     */
    public static function generatePassword(string $shortcode, string $passkey, string $timestamp): string
    {
        return base64_encode($shortcode.$passkey.$timestamp);
    }

    /**
     * Generate Security Credential for B2C/B2B/Reversal/Status operations.
     *
     * @param  string  $password  Initiator password
     * @param  string  $certPath  Path to certificate file
     * @return string Base64 encoded security credential
     *
     * @throws MpesaConfigurationException
     */
    public static function generateSecurityCredential(string $password, string $certPath): string
    {
        if (! file_exists($certPath)) {
            throw MpesaConfigurationException::invalidConfig(
                'security.certificates',
                "Certificate file not found at: {$certPath}"
            );
        }

        $pubKey = file_get_contents($certPath);
        openssl_public_encrypt($password, $encrypted, $pubKey, OPENSSL_PKCS1_PADDING);

        return base64_encode($encrypted);
    }

    /**
     * Get certificate path based on environment.
     *
     * @param  string  $environment  'sandbox' or 'production'
     * @return string Path to certificate file
     */
    public static function getCertificatePath(string $environment): string
    {
        return config("mpesa.security.certificates.{$environment}");
    }
}
