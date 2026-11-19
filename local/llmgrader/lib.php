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
