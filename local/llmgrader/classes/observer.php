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
 * Event observer for LLM Grader plugin.
 *
 * Listens for quiz submission events and triggers asynchronous grading tasks.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader;

use core\task\manager;
use local_llmgrader\task\grade_attempt;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer class.
 */
class observer {

    /**
     * Handle quiz attempt submission event.
     *
     * Inspects the submitted attempt for eligible Essay questions and queues
     * asynchronous grading tasks.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function handle_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        // Verify plugin is enabled.
        if (!get_config('local_llmgrader', 'enabled')) {
            return;
        }

        $attemptid = $event->objectid;
        $quizid = $event->get_data()['other']['quizid'] ?? 0;

        if (!$attemptid || !$quizid) {
            return;
        }

        // Queue the grading task. The task will inspect individual questions.
        $task = grade_attempt::instance($attemptid, $quizid);
        manager::queue_adhoc_task($task);
    }
}
