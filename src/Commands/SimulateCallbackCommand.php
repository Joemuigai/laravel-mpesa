<?php

namespace Joemuigai\LaravelMpesa\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Joemuigai\LaravelMpesa\Testing\MockResponses;

class SimulateCallbackCommand extends Command
{
    protected $signature = 'mpesa:simulate-callback
                            {--type=stk_push : Callback type (stk_push, b2c, c2b)}
                            {--status=success : Callback status (success, failed, timeout)}
                            {--checkout-request-id= : Checkout Request ID for STK Push}
                            {--conversation-id= : Conversation ID for B2C}
                            {--transaction-id= : Transaction ID for C2B}
                            {--amount=100 : Transaction amount}
                            {--url= : Custom callback URL (defaults to config)}';

    protected $description = 'Simulate an M-Pesa callback for testing';

    public function handle(): int
    {
        $type = $this->option('type');
        $status = $this->option('status');
        $url = $this->option('url');

        // Get callback URL from config if not provided
        if (! $url) {
            $url = match ($type) {
                'stk_push' => config('mpesa.stk.callback_url'),
                'b2c' => config('mpesa.b2c.result_url'),
                'c2b' => config('mpesa.c2b.confirmation_url'),
                default => null,
            };
        }

        if (! $url) {
            $this->error("No callback URL found for type: {$type}");
            $this->info('Please specify a URL using --url or configure it in mpesa.php');

            return self::FAILURE;
        }

        // Generate mock payload
        $payload = $this->generatePayload($type, $status);

        $this->info("Simulating {$type} callback ({$status}) to: {$url}");
        $this->line('');
        $this->line('Payload:');
        $this->line(json_encode($payload, JSON_PRETTY_PRINT));
        $this->line('');

        // Send the callback
        try {
            $response = Http::post($url, $payload);

            if ($response->successful()) {
                $this->info('✓ Callback sent successfully!');
                $this->line("Response Code: {$response->status()}");
                $this->line("Response Body: {$response->body()}");

                return self::SUCCESS;
            } else {
                $this->error('✗ Callback failed!');
                $this->line("Response Code: {$response->status()}");
                $this->line("Response Body: {$response->body()}");

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('✗ Error sending callback:');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function generatePayload(string $type, string $status): array
    {
        return match ($type) {
            'stk_push' => $this->generateStkPushPayload($status),
            'b2c' => $this->generateB2CPayload($status),
            'c2b' => $this->generateC2BPayload($status),
            default => [],
        };
    }

    protected function generateStkPushPayload(string $status): array
    {
        $checkoutRequestId = $this->option('checkout-request-id') ?? 'ws_CO_'.now()->timestamp;

        $resultCode = match ($status) {
            'success' => 0,
            'failed' => 1,
            'timeout' => 1032,
            default => 1,
        };

        return MockResponses::stkPushCallback($checkoutRequestId, $resultCode);
    }

    protected function generateB2CPayload(string $status): array
    {
        $conversationId = $this->option('conversation-id') ?? 'AG_'.now()->timestamp;

        $resultCode = match ($status) {
            'success' => 0,
            default => 1,
        };

        return MockResponses::b2cCallback($conversationId, $resultCode);
    }

    protected function generateC2BPayload(string $status): array
    {
        $transId = $this->option('transaction-id') ?? 'MOCK'.now()->timestamp;
        $amount = (float) $this->option('amount');

        return MockResponses::c2bCallback($transId, $amount);
    }
}
