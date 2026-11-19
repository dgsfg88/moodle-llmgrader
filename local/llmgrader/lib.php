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

    // Get course module ID for the quiz.
    global $DB;
    $cm = $DB->get_record('course_modules', ['instance' => $quiz->id, 'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'])]);
    if (!$cm) {
        return;
    }

    $url = new \moodle_url('/local/llmgrader/quiz_config.php', ['cmid' => $cm->id]);
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

/**
 * Extend the admin settings navigation.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param context_system $context The system context.
 */
function local_llmgrader_extend_navigation_admin(\navigation_node $navigation, \context_system $context): void {
    // Check if user has site config capability.
    if (!has_capability('moodle/site:config', $context)) {
        return;
    }

    // Check if the localplugins category exists in navigation.
    $localplugins = $navigation->get('localplugins');
    if (!$localplugins) {
        return;
    }

    // Add LLM Grader category under local plugins.
    $llmgradercategory = \navigation_node::create(
        get_string('pluginname', 'local_llmgrader'),
        null,
        \navigation_node::TYPE_CATEGORY,
        get_string('pluginname', 'local_llmgrader'),
        'llmgrader_admin',
        new \pix_icon('i/settings', '')
    );

    // Add global settings link.
    $settingsurl = new \moodle_url('/admin/settings.php', ['section' => 'local_llmgrader']);
    $llmgradercategory->add_node(\navigation_node::create(
        get_string('settings', 'local_llmgrader'),
        $settingsurl,
        \navigation_node::TYPE_SETTING,
        get_string('settings', 'local_llmgrader'),
        'llmgrader_global_settings',
        new \pix_icon('i/settings', '')
    ));

    // Add course configuration link.
    $courseconfigurl = new \moodle_url('/local/llmgrader/course_config.php', ['id' => 0]);
    $llmgradercategory->add_node(\navigation_node::create(
        get_string('configurecourses', 'local_llmgrader'),
        $courseconfigurl,
        \navigation_node::TYPE_SETTING,
        get_string('configurecourses', 'local_llmgrader'),
        'llmgrader_course_config_admin',
        new \pix_icon('i/settings', '')
    ));

    $localplugins->add_node($llmgradercategory);
}
