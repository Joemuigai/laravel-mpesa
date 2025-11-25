<?php

namespace Joemuigai\LaravelMpesa\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyMpesaCallback
{
    /**
     * Safaricom's official gateway IP addresses.
     *
     * @var array<string>
     */
    protected array $safaricomIps = [
        '196.201.214.200',
        '196.201.214.206',
        '196.201.213.114',
        '196.201.214.207',
        '196.201.214.208',
        '196.201.213.44',
        '196.201.212.127',
        '196.201.212.138',
        '196.201.212.129',
        '196.201.212.136',
        '196.201.212.74',
        '196.201.212.69',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if IP verification is enabled
        if (! config('mpesa.callbacks.security.verify_ip', true)) {
            return $next($request);
        }

        $clientIp = $request->ip();
        $allowedIps = config('mpesa.callbacks.security.allowed_ips', $this->safaricomIps);

        // Verify the request comes from allowed IPs
        if (! in_array($clientIp, $allowedIps)) {
            Log::warning('M-Pesa callback rejected: Invalid IP', [
                'ip' => $clientIp,
                'url' => $request->url(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Unauthorized',
            ], 403);
        }

        // Optional: Verify signature if enabled
        if (config('mpesa.callbacks.security.verify_signature', false)) {
            if (! $this->verifySignature($request)) {
                Log::warning('M-Pesa callback rejected: Invalid signature', [
                    'ip' => $clientIp,
                    'url' => $request->url(),
                ]);

                return response()->json([
                    'ResultCode' => 1,
                    'ResultDesc' => 'Invalid signature',
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * Verify the callback signature.
     *
     * Note: M-Pesa doesn't currently provide signature verification,
     * but this method is here for future use if they add it.
     */
    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Mpesa-Signature');

        if (! $signature) {
            return false;
        }

        $secret = config('mpesa.callbacks.security.webhook_secret');

        if (! $secret) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
