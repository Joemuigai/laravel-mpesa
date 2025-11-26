<?php

namespace Joemuigai\LaravelMpesa\Support;

class ExponentialBackoff
{
    /**
     * Calculate exponential backoff delay with jitter.
     *
     * @param  int  $attempt  Current retry attempt (0-indexed)
     * @param  int  $baseDelay  Base delay in milliseconds (default: 100ms)
     * @param  int  $maxDelay  Maximum delay in milliseconds (default: 10000ms = 10s)
     * @param  bool  $useJitter  Whether to add random jitter (default: true)
     * @return int Delay in milliseconds
     */
    public static function calculate(
        int $attempt,
        int $baseDelay = 100,
        int $maxDelay = 10000,
        bool $useJitter = true
    ): int {
        // Calculate exponential delay: baseDelay * (2 ^ attempt)
        $delay = $baseDelay * (2 ** $attempt);

        // Cap at maximum delay
        $delay = min($delay, $maxDelay);

        // Add jitter (random value between 0 and delay)
        if ($useJitter) {
            $jitter = random_int(0, $delay);
            $delay = (int) ($delay / 2 + $jitter / 2);
        }

        return $delay;
    }

    /**
     * Get delays for all retry attempts.
     *
     * @param  int  $maxAttempts  Maximum number of retry attempts
     * @param  int  $baseDelay  Base delay in milliseconds
     * @param  int  $maxDelay  Maximum delay in milliseconds
     * @return array<int> Array of delays in milliseconds
     */
    public static function getDelays(
        int $maxAttempts,
        int $baseDelay = 100,
        int $maxDelay = 10000
    ): array {
        $delays = [];

        for ($i = 0; $i < $maxAttempts; $i++) {
            $delays[] = self::calculate($i, $baseDelay, $maxDelay, false);
        }

        return $delays;
    }
}
