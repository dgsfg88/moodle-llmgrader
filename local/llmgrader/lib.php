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
 * Shared library functions for LLM Grader plugin.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get recent grading activity for the administration page.
 *
 * @param int $limit Maximum number of records to return.
 * @return array Array of grading log records.
 */
function local_llmgrader_get_recent_grading(int $limit = 20): array {
    global $DB;

    return $DB->get_records('local_llmgrader_log', [], 'timecreated DESC', '*', 0, $limit);
}

/**
 * Get grading statistics.
 *
 * @return object Statistics object.
 */
function local_llmgrader_get_grading_stats(): object {
    global $DB;

    $stats = new stdClass();
    $stats->total = $DB->count_records('local_llmgrader_log');
    $stats->completed = $DB->count_records('local_llmgrader_log', ['status' => 'completed']);
    $stats->failed = $DB->count_records('local_llmgrader_log', ['status' => 'failed']);
    $stats->pending = $DB->count_records('local_llmgrader_log', ['status' => 'pending']);
    $stats->retry_scheduled = $DB->count_records('local_llmgrader_log', ['status' => 'retry_scheduled']);

    return $stats;
}

/**
 * Extend the course administration menu.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param stdClass $course The course to which navigation is being added.
 * @param context_course $context The course context.
 */
function local_llmgrader_extend_navigation_course(\navigation_node $navigation, \stdClass $course, \context_course $context): void {
    // Check if user has capability to view or configure.
    if (!has_capability('local/llmgrader:viewconfiguration', $context) &&
        !has_capability('local/llmgrader:configurecourse', $context)) {
        return;
    }

    // Check if plugin is enabled at any level.
    if (!\local_llmgrader\local\config_provider::is_enabled(0, $course->id)) {
        return;
    }

    $url = new \moodle_url('/local/llmgrader/course_config.php', ['id' => $course->id]);
    $node = \navigation_node::create(
        get_string('pluginname', 'local_llmgrader'),
        $url,
        \navigation_node::TYPE_SETTING,
        get_string('pluginname', 'local_llmgrader'),
        'llmgrader_course_config',
        new \pix_icon('i/settings', '')
    );
    $navigation->add_node($node);
}

/**
 * Extend the quiz administration menu.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param stdClass $quiz The quiz to which navigation is being added.
 * @param context_module $context The quiz context.
 */
function local_llmgrader_extend_navigation_quiz(\navigation_node $navigation, \stdClass $quiz, \context_module $context): void {
    // Check if user has capability to view or configure.
    if (!has_capability('local/llmgrader:viewconfiguration', $context) &&
        !has_capability('local/llmgrader:configurequiz', $context)) {
        return;
    }

    // Get course ID for config check.
    $courseid = $quiz->course;

    // Check if plugin is enabled at any level.
    if (!\local_llmgrader\local\config_provider::is_enabled($quiz->id, $courseid)) {
        return;
    }

    $url = new \moodle_url('/local/llmgrader/quiz_config.php', ['id' => $quiz->id]);
    $node = \navigation_node::create(
        get_string('pluginname', 'local_llmgrader'),
        $url,
        \navigation_node::TYPE_SETTING,
        get_string('pluginname', 'local_llmgrader'),
        'llmgrader_quiz_config',
        new \pix_icon('i/settings', '')
    );
    $navigation->add_node($node);
}
