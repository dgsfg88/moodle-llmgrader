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
use local_llmgrader\local\config_provider;
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
        $attemptid = $event->objectid;
        $quizid = $event->get_data()['other']['quizid'] ?? 0;

        if (!$attemptid || !$quizid) {
            return;
        }

        // Get course ID from quiz.
        global $DB;
        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'course', IGNORE_MISSING);
        if (!$quiz) {
            return;
        }
        $courseid = (int)$quiz->course;

        // Check if LLM grading is enabled at any level in the inheritance chain.
        if (!config_provider::is_enabled($quizid, $courseid)) {
            return;
        }

        // Queue the grading task with quiz and course IDs for config resolution.
        $task = grade_attempt::instance($attemptid, $quizid, $courseid);
        manager::queue_adhoc_task($task);
    }
}
