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
 * Database upgrade script for LLM Grader plugin.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Perform database upgrades for the llmgrader plugin.
 *
 * @param int $oldversion The version we are upgrading from
 * @return bool Always returns true
 */
function xmldb_llmgrader_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026111800) {
        // Create the local_llmgrader_log table.
        $table = new xmldb_table('local_llmgrader_log');

        $table->add_field('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('attemptid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
        $table->add_field('slot', XMLDB_TYPE_INTEGER, 5, null, XMLDB_NOTNULL, null, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
        $table->add_field('questionversion', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, 30, null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('model', XMLDB_TYPE_CHAR, 100, null, null, null, null);
        $table->add_field('score', XMLDB_TYPE_NUMBER, '5,2', null, null, null, null);
        $table->add_field('maxscore', XMLDB_TYPE_NUMBER, '5,2', null, null, null, null);
        $table->add_field('feedback', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('retrycount', XMLDB_TYPE_INTEGER, 3, null, XMLDB_NOTNULL, null, 0);
        $table->add_field('nextretry', XMLDB_TYPE_INTEGER, 10, null, null, null, null);
        $table->add_field('lasthttpcode', XMLDB_TYPE_INTEGER, 5, null, null, null, null);
        $table->add_field('lastretryreason', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, 10, null, null, null, null);
        $table->add_field('error', XMLDB_TYPE_TEXT, null, null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('attemptid', XMLDB_INDEX_NOTUNIQUE, ['attemptid']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('nextretry', XMLDB_INDEX_NOTUNIQUE, ['nextretry']);
        $table->add_index('unique_op', XMLDB_INDEX_UNIQUE, ['attemptid', 'slot', 'questionid', 'questionversion']);

        if (!$dbman->table_exists($table)) {
            $dbman->add_table($table);
        }

        // Mark version as upgraded.
        upgrade_plugin_savepoint(true, 2026111800, 'local', 'llmgrader');
    }

    return true;
}
