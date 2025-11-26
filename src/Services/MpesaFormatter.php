<?php

namespace Joemuigai\LaravelMpesa\Services;

class MpesaFormatter
{
    /**
     * Format phone number to Kenyan standard (2547XXXXXXXX or 2541XXXXXXXX).
     *
     * @param  string  $number  Phone number in various formats
     * @return string Formatted phone number with country code
     */
    public static function formatPhoneNumber(string $number): string
    {
        // Remove non-numeric characters
        $number = preg_replace('/\D/', '', $number);

        // If starts with 0, replace with 254
        if (str_starts_with($number, '0')) {
            return '254'.substr($number, 1);
        }

        // If starts with 7 or 1 (and is 9 digits), prepend 254
        if ((str_starts_with($number, '7') || str_starts_with($number, '1')) && strlen($number) === 9) {
            return '254'.$number;
        }

        // If starts with 254, return as is
        if (str_starts_with($number, '254')) {
            return $number;
        }

        return $number;
    }

    /**
     * Format amount to integer (M-Pesa expects amounts as integers).
     *
     * @param  int|float|string  $amount
     */
    public static function formatAmount($amount): int
    {
        return (int) $amount;
    }

    /**
     * Generate timestamp in M-Pesa format (YmdHis).
     *
     * @return string Timestamp in YmdHis format
     */
    public static function generateTimestamp(): string
    {
        return now()->format('YmdHis');
    }
}
