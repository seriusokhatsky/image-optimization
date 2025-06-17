<?php

/**
 * Format Helper
 *
 * Helper class for formatting various data types.
 *
 * @category Helpers
 * @package  Optimizer
 * @author   Your Name <your@email.com>
 * @license  MIT License
 * @link     https://yourprojecturl.com
 */

namespace App\Helpers;

/**
 * Format Helper Class
 *
 * Provides static methods for formatting data.
 *
 * @category Helpers
 * @package  Optimizer
 * @author   Your Name <your@email.com>
 * @license  MIT License
 * @link     https://yourprojecturl.com
 */
class FormatHelper
{
    /**
     * Format bytes into human readable format.
     *
     * @param int $bytes     The number of bytes to format
     * @param int $precision The number of decimal places
     *
     * @return string The formatted byte string
     */
    public static function bytes(int $bytes, int $precision = 2): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, $precision) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, $precision) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, $precision) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
} 