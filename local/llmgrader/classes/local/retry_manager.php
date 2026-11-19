<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Retry manager for adaptive retry scheduling.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Retry manager class.
 *
 * Classifies errors, determines retryability, and calculates backoff delays.
 */
class retry_manager {

    /** @var int Base delay in seconds */
    protected int $basedelay;

    /** @var int Maximum normal backoff in seconds */
    protected int $maxdelay;

    /** @var bool Is retry enabled? */
    protected bool $retryenabled;

    /**
     * Constructor.
     *
     * @param object|null $config Optional configuration object. If null, uses global config.
     */
    public function __construct(?object $config = null) {
        if ($config) {
            $this->basedelay = (int)($config->base_retry_delay ?? 5);
            $this->maxdelay = (int)($config->max_backoff_delay ?? 900);
            $this->retryenabled = (bool)($config->retry_enabled ?? 1);
        } else {
            $this->basedelay = (int)(get_config('local_llmgrader', 'base_retry_delay') ?: 5);
            $this->maxdelay = (int)(get_config('local_llmgrader', 'max_backoff_delay') ?: 900);
            $this->retryenabled = (bool)(get_config('local_llmgrader', 'retry_enabled') ?: 1);
        }
    }

    /**
     * Determine if an exception represents a retryable error.
     *
     * @param \Throwable $e
     * @return bool
     */
    public function is_retryable(\Throwable $e): bool {
        if (!$this->retryenabled) {
            return false;
        }

        $message = $e->getMessage();

        // Retry on HTTP 429 (rate limiting).
        if (strpos($message, 'HTTP 429') !== false || strpos($message, 'ratelimited') !== false) {
            return true;
        }

        // Retry on server errors.
        if (strpos($message, 'HTTP 500') !== false ||
            strpos($message, 'HTTP 502') !== false ||
            strpos($message, 'HTTP 503') !== false ||
            strpos($message, 'HTTP 504') !== false) {
            return true;
        }

        // Retry on HTTP 408 (timeout).
        if (strpos($message, 'HTTP 408') !== false) {
            return true;
        }

        // Retry on connection failures / timeouts.
        if (strpos($message, 'Connection refused') !== false ||
            strpos($message, 'Connection timed out') !== false ||
            strpos($message, 'cURL error') !== false ||
            strpos($message, 'timeout') !== false) {
            return true;
        }

        // Retry on invalid LLM response (might be transient).
        if (strpos($message, 'invalidllmresponse') !== false ||
            strpos($message, 'invalidjson') !== false) {
            return true;
        }

        // Do NOT retry on permanent failures.
        if (strpos($message, 'HTTP 400') !== false ||
            strpos($message, 'HTTP 401') !== false ||
            strpos($message, 'HTTP 403') !== false ||
            strpos($message, 'noapiendpoint') !== false) {
            return false;
        }

        // Default: retry on network-related errors.
        return (strpos($e->getMessage(), 'HTTP') !== false ||
                strpos($e->getMessage(), 'cURL') !== false ||
                strpos($e->getMessage(), 'Connection') !== false);
    }

    /**
     * Calculate the delay for the next retry using exponential backoff with jitter.
     *
     * Formula: delay = min(max_delay, base_delay * 2^retry_count) + jitter
     *
     * @param int $retrycount The current retry count (0-based).
     * @return int Delay in seconds.
     */
    public function get_delay(int $retrycount): int {
        // Calculate exponential backoff.
        $exponential = min($this->maxdelay, $this->basedelay * pow(2, $retrycount));

        // Add jitter (random value between 0 and 1 second).
        $jitter = rand(0, 1000) / 1000;

        $delay = (int)ceil($exponential + $jitter);

        // Protect against integer overflow.
        if ($delay <= 0 || !is_finite($delay)) {
            $delay = $this->basedelay;
        }

        return $delay;
    }

    /**
     * Parse Retry-After header value to seconds.
     *
     * Supports integer seconds and HTTP-date format.
     *
     * @param string $header The Retry-After header value.
     * @return int Seconds to wait.
     */
    public function parse_retry_after(string $header): int {
        // Try integer seconds first.
        if (ctype_digit($header)) {
            return (int)$header;
        }

        // Try HTTP-date format.
        $timestamp = strtotime($header);
        if ($timestamp !== false) {
            $delay = $timestamp - time();
            return max(1, $delay);
        }

        // Fallback to base delay.
        return $this->basedelay;
    }
}
