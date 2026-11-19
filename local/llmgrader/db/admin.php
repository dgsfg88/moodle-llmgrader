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
 * Administration tree structure for LLM Grader plugin.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_llmgrader', new lang_string('pluginname', 'local_llmgrader')));
    
    // Global settings page.
    $settings = new admin_settingpage('local_llmgrader_settings', new lang_string('pluginname', 'local_llmgrader'));
    
    if ($ADMIN->fulltree) {
        // Include settings from settings.php.
        include __DIR__ . '/../settings.php';
    }
    
    $ADMIN->add('local_llmgrader', $settings);
    
    // Course configuration page (accessible from admin area for managers).
    $courseconfig = new admin_externalpage(
        'local_llmgrader_course_config',
        new lang_string('configurecourses', 'local_llmgrader'),
        new moodle_url('/local/llmgrader/course_config.php', ['id' => 0]),
        'local/llmgrader:configurecourse'
    );
    $ADMIN->add('local_llmgrader', $courseconfig);
}
