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
 * Database upgrade script for LLM Grader plugin - multi-level configuration.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute upgrade from previous version.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Always true.
 * @throws \dml_exception
 */
function xmldb_local_llmgrader_upgrade($oldversion) {
    global $DB;

    $dbmanager = $DB->get_manager();

    // Upgrade from version before multi-level config (2026111800 or earlier).
    if ($oldversion < 2026111900) {

        // --- Clean up stale scheduled task records ---
        // grade_attempt and retry_grading are adhoc tasks only, not scheduled.
        // Remove any leftover scheduled task entries from previous versions.
        $DB->delete_records('task_scheduled', [
            'component' => 'local_llmgrader',
            'classname' => 'local_llmgrader\task\grade_attempt',
        ]);
        $DB->delete_records('task_scheduled', [
            'component' => 'local_llmgrader',
            'classname' => 'local_llmgrader\task\retry_grading',
        ]);

        // --- Add new tables ---

        // Table: local_llmgrader_course_config.
        if (!$dbmanager->table_exists('local_llmgrader_course_config')) {
            $table = new xmldb_table('local_llmgrader_course_config');

            $table->add_field('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, '0');
            $table->add_field('api_endpoint', XMLDB_TYPE_CHAR, 512, null, null, null, null);
            $table->add_field('api_key', XMLDB_TYPE_CHAR, 512, null, null, null, null);
            $table->add_field('model', XMLDB_TYPE_CHAR, 100, null, null, null, null);
            $table->add_field('temperature', XMLDB_TYPE_NUMBER, '3,2', null, null, null, '0.20');
            $table->add_field('max_tokens', XMLDB_TYPE_INTEGER, 10, null, null, null, null);
            $table->add_field('http_timeout', XMLDB_TYPE_INTEGER, 10, null, null, null, null);
            $table->add_field('system_prompt', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('feedback_language', XMLDB_TYPE_CHAR, 10, null, null, null, null);
            $table->add_field('retry_enabled', XMLDB_TYPE_INTEGER, 1, null, null, null, '1');
            $table->add_field('max_retries', XMLDB_TYPE_INTEGER, 3, null, null, null, '8');
            $table->add_field('base_retry_delay', XMLDB_TYPE_INTEGER, 10, null, null, null, '5');
            $table->add_field('max_backoff_delay', XMLDB_TYPE_INTEGER, 10, null, null, null, '900');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('courseid', XMLDB_INDEX_UNIQUE, ['courseid']);

            $dbmanager->create_table($table);
        }

        // Table: local_llmgrader_quiz_config.
        if (!$dbmanager->table_exists('local_llmgrader_quiz_config')) {
            $table = new xmldb_table('local_llmgrader_quiz_config');

            $table->add_field('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('quizid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, '0');
            $table->add_field('api_endpoint', XMLDB_TYPE_CHAR, 512, null, null, null, null);
            $table->add_field('api_key', XMLDB_TYPE_CHAR, 512, null, null, null, null);
            $table->add_field('model', XMLDB_TYPE_CHAR, 100, null, null, null, null);
            $table->add_field('temperature', XMLDB_TYPE_NUMBER, '3,2', null, null, null, '0.20');
            $table->add_field('max_tokens', XMLDB_TYPE_INTEGER, 10, null, null, null, null);
            $table->add_field('http_timeout', XMLDB_TYPE_INTEGER, 10, null, null, null, null);
            $table->add_field('system_prompt', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('feedback_language', XMLDB_TYPE_CHAR, 10, null, null, null, null);
            $table->add_field('retry_enabled', XMLDB_TYPE_INTEGER, 1, null, null, null, '1');
            $table->add_field('max_retries', XMLDB_TYPE_INTEGER, 3, null, null, null, '8');
            $table->add_field('base_retry_delay', XMLDB_TYPE_INTEGER, 10, null, null, null, '5');
            $table->add_field('max_backoff_delay', XMLDB_TYPE_INTEGER, 10, null, null, null, '900');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('quizid', XMLDB_INDEX_UNIQUE, ['quizid']);
            $table->add_index('courseid', XMLDB_INDEX_NORMAL, ['courseid']);

            $dbmanager->create_table($table);
        }

        // --- Alter log table: add configlevel and configid fields ---

        if ($dbmanager->table_exists('local_llmgrader_log')) {
            $table = $dbmanager->get_table('local_llmgrader_log');

            // Add configlevel field.
            if (!$table->has_field('configlevel')) {
                $field = new xmldb_field('configlevel', XMLDB_TYPE_CHAR, 10, null, null, null, null, 'error');
                $dbmanager->add_field($table, $field);
            }

            // Add configid field.
            if (!$table->has_field('configid')) {
                $field = new xmldb_field('configid', XMLDB_TYPE_INTEGER, 10, null, null, null, null, 'configlevel');
                $dbmanager->add_field($table, $field);
            }

            // Add index for configlevel + configid.
            if (!$table->has_index('configlevel')) {
                $index = new xmldb_index('configlevel', XMLDB_INDEX_NOTUNIQUE, ['configlevel', 'configid']);
                $dbmanager->add_index($table, $index);
            }
        }

        // Mark upgrade as complete.
        set_config('upgradedtomultilevel', '1', 'local_llmgrader');
        upgrade_plugin_savepoint(true, 2026111900, 'local', 'llmgrader');
    }

    // Fix TYPE_NUMBER fields in log table (LENGTH/DECIMALS instead of raw number).
    if ($oldversion < 2026111901) {

        if ($dbmanager->table_exists('local_llmgrader_log')) {
            $table = $dbmanager->get_table('local_llmgrader_log');

            // Fix score field: convert from TYPE_NUMBER to TYPE_DOUBLE.
            if ($table->has_field('score') && $table->getField('score')->getType() === XMLDB_TYPE_NUMBER) {
                $field = new xmldb_field('score', XMLDB_TYPE_DOUBLE, 10, null, null, null, null);
                $dbmanager->change_field_type($table, $field);
            }

            // Fix maxscore field: convert from TYPE_NUMBER to TYPE_DOUBLE.
            if ($table->has_field('maxscore') && $table->getField('maxscore')->getType() === XMLDB_TYPE_NUMBER) {
                $field = new xmldb_field('maxscore', XMLDB_TYPE_DOUBLE, 10, null, null, null, null);
                $dbmanager->change_field_type($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026111901, 'local', 'llmgrader');
    }

    // Version 1.2.0: grading pipeline fixes (no schema changes).
    if ($oldversion < 2026112100) {
        upgrade_plugin_savepoint(true, 2026112100, 'local', 'llmgrader');
    }

    // Version 1.3.2: manual re-grading page (no schema changes).
    if ($oldversion < 2026112302) {
        upgrade_plugin_savepoint(true, 2026112302, 'local', 'llmgrader');
    }

    return true;
}
