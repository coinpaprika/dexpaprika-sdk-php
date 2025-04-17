<?php

namespace DexPaprika\Utils;

class Formatter
{
    /**
     * Format a volume value to a human-readable string
     *
     * @param float $volume The volume value
     * @param int $decimals The number of decimal places
     * @return string The formatted volume string
     */
    public static function formatVolume(float $volume, int $decimals = 2): string
    {
        if ($volume >= 1_000_000_000) {
            return '$' . number_format($volume / 1_000_000_000, $decimals) . 'B';
        } elseif ($volume >= 1_000_000) {
            return '$' . number_format($volume / 1_000_000, $decimals) . 'M';
        } elseif ($volume >= 1_000) {
            return '$' . number_format($volume / 1_000, $decimals) . 'K';
        } else {
            return '$' . number_format($volume, $decimals);
        }
    }

    /**
     * Format a percentage change value
     *
     * @param float $change The percentage change value
     * @param int $decimals The number of decimal places
     * @return string The formatted percentage change string
     */
    public static function formatChange(float $change, int $decimals = 2): string
    {
        $prefix = $change >= 0 ? '+' : '';
        return $prefix . number_format($change, $decimals) . '%';
    }

    /**
     * Format a token pair from tokens array
     *
     * @param array<int, array<string, mixed>> $tokens Array of token objects
     * @return string The formatted token pair string
     */
    public static function formatPair(array $tokens): string
    {
        if (count($tokens) < 2) {
            return 'Unknown Pair';
        }
        
        return $tokens[0]['symbol'] . '/' . $tokens[1]['symbol'];
    }

    /**
     * Format a price value
     *
     * @param float $price The price value
     * @param int $decimals The number of decimal places
     * @return string The formatted price string
     */
    public static function formatPrice(float $price, int $decimals = 6): string
    {
        // Use fewer decimals for larger numbers
        if ($price >= 1000) {
            $decimals = min(2, $decimals);
        } elseif ($price >= 1) {
            $decimals = min(4, $decimals);
        }
        
        return '$' . number_format($price, $decimals);
    }

    /**
     * Format a date string to a more readable format
     *
     * @param string $dateString The date string
     * @param string $format The desired output format
     * @return string The formatted date string
     */
    public static function formatDate(string $dateString, string $format = 'Y-m-d H:i:s'): string
    {
        $date = new \DateTime($dateString);
        return $date->format($format);
    }
} 