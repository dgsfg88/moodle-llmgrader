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
 * Unit tests for observer.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_llmgrader;

/**
 * Unit tests for observer.
 *
 * @group local_llmgrader
 */
class observer_test extends \advanced_testcase {

    /**
     * Test that the observer queues a task when plugin is enabled.
     */
    public function test_observer_queues_task_when_enabled(): void {
        // Enable the plugin.
        set_config('enabled', 1, 'local_llmgrader');

        // Create a mock event with required data.
        $eventdata = [
            'context' => context_system::instance(),
            'objectid' => 1, // Attempt ID.
            'relateduserid' => 2, // User ID.
            'courseid' => 3,
            'other' => ['quizid' => 4],
        ];

        $event = \mod_quiz\event\attempt_submitted::create($eventdata);

        // Capture queued tasks before and after.
        $before = \core\task\manager::get_adhoc_task_count();

        observer::handle_attempt_submitted($event);

        $after = \core\task\manager::get_adhoc_task_count();

        // A task should have been queued.
        $this->assertGreaterThanOrEqual($before, $after - 1);
    }

    /**
     * Test that the observer does nothing when plugin is disabled.
     */
    public function test_observer_does_nothing_when_disabled(): void {
        // Disable the plugin.
        set_config('enabled', 0, 'local_llmgrader');

        $eventdata = [
            'context' => context_system::instance(),
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => 3,
            'other' => ['quizid' => 4],
        ];

        $event = \mod_quiz\event\attempt_submitted::create($eventdata);

        $before = \core\task\manager::get_adhoc_task_count();

        observer::handle_attempt_submitted($event);

        $after = \core\task\manager::get_adhoc_task_count();

        // No task should have been queued.
        $this->assertEquals($before, $after);
    }
}
