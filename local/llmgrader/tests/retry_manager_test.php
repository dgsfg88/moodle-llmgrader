<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
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
 * Unit tests for retry manager.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_llmgrader;

use local_llmgrader\local\retry_manager;

/**
 * Unit tests for retry manager.
 *
 * @group local_llmgrader
 */
class retry_manager_test extends \advanced_testcase {

    /**
     * Test HTTP 429 is retryable.
     */
    public function test_http_429_is_retryable(): void {
        $manager = new retry_manager();
        $e = new \moodle_exception('test', 'local_llmgrader', '', 'HTTP 429');

        $this->assertTrue($manager->is_retryable($e));
    }

    /**
     * Test HTTP 503 is retryable.
     */
    public function test_http_503_is_retryable(): void {
        $manager = new retry_manager();
        $e = new \moodle_exception('test', 'local_llmgrader', '', 'HTTP 503');

        $this->assertTrue($manager->is_retryable($e));
    }

    /**
     * Test HTTP 401 is NOT retryable.
     */
    public function test_http_401_not_retryable(): void {
        $manager = new retry_manager();
        $e = new \moodle_exception('test', 'local_llmgrader', '', 'HTTP 401');

        $this->assertFalse($manager->is_retryable($e));
    }

    /**
     * Test HTTP 400 is NOT retryable.
     */
    public function test_http_400_not_retryable(): void {
        $manager = new retry_manager();
        $e = new \moodle_exception('test', 'local_llmgrader', '', 'HTTP 400');

        $this->assertFalse($manager->is_retryable($e));
    }

    /**
     * Test exponential backoff delay calculation.
     */
    public function test_exponential_backoff(): void {
        // Set up a retry manager with known values.
        $manager = new retry_manager();
        $delay0 = $manager->get_delay(0);
        $delay1 = $manager->get_delay(1);
        $delay2 = $manager->get_delay(2);

        // Each successive delay should be >= the previous (due to exponential growth).
        $this->assertGreaterThan(0, $delay0);
        $this->assertGreaterThanOrEqual($delay0, $delay1);
        $this->assertGreaterThanOrEqual($delay1, $delay2);
    }

    /**
     * Test Retry-After header parsing (integer).
     */
    public function test_parse_retry_after_integer(): void {
        $manager = new retry_manager();
        $delay = $manager->parse_retry_after('30');

        $this->assertEquals(30, $delay);
    }

    /**
     * Test Retry-After header parsing (HTTP date).
     */
    public function test_parse_retry_after_http_date(): void {
        $manager = new retry_manager();
        // Set a date 60 seconds in the future.
        $futuredate = gmdate('D, d M Y H:i:s', time() + 60) . ' GMT';
        $delay = $manager->parse_retry_after($futuredate);

        $this->assertEquals(60, $delay);
    }

    /**
     * Test delay is capped at max_backoff_delay.
     */
    public function test_delay_capped_at_max(): void {
        // With a very high retry count, delay should not exceed max (900 by default).
        $manager = new retry_manager();
        $delay = $manager->get_delay(20); // 5 * 2^20 would be huge.

        $this->assertLessThanOrEqual(901, $delay); // +1 for jitter.
    }
}
