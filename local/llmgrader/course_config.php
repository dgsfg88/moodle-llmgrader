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
 * Course-level LLM Grader configuration page.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();

use local_llmgrader\local\config_provider;
use local_llmgrader\local\course_config_manager;
use local_llmgrader\form\course_config_form;

$courseid = required_param('id', PARAM_INT);
$save = optional_param('save', null, PARAM_RAW);
$reset = optional_param('resettoinherit', 0, PARAM_INT);
$page = optional_param('page', 'config', PARAM_ALPHA);

global $DB;
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$coursecontext = \context_course::instance($courseid);

require_login($course, false);

$hasconfigure = has_capability('local/llmgrader:configurecourse', $coursecontext);
$hasview = has_capability('local/llmgrader:viewconfiguration', $coursecontext);

if (!$hasconfigure && !$hasview) {
    require_capability('moodle/course:view', $coursecontext);
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]), get_string('nopermissions', 'local_llmgrader'), 0, \core\session\manager::get_session(), \core\session\manager::get_login_url());
}

// Build page URL and navigation.
$url = new moodle_url('/local/llmgrader/course_config.php', ['id' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($coursecontext);
$PAGE->set_title(format_string($course->shortname) . ': ' . get_string('courseconfigheading', 'local_llmgrader'));
$PAGE->set_heading(format_string($course->full_name));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('courseconfigheading', 'local_llmgrader'));

// Navigation tabs.
echo '<div class="tabtree"><ul class="nav nav-tabs">';
$configurl = new moodle_url('/local/llmgrader/course_config.php', ['id' => $courseid, 'page' => 'config']);
$logurl = new moodle_url('/local/llmgrader/course_config.php', ['id' => $courseid, 'page' => 'logs']);

echo '<li class="nav-item"><a class="nav-link ' . ($page === 'config' ? 'active' : '') . '" href="' . $configurl->out() . '">' .
    get_string('settings', 'local_llmgrader') . '</a></li>';
echo '<li class="nav-item"><a class="nav-link ' . ($page === 'logs' ? 'active' : '') . '" href="' . $logurl->out() . '">' .
    get_string('gradinglogs', 'local_llmgrader') . '</a></li>';
echo '</ul></div>';

if ($page === 'logs') {
    // Show grading logs for this course.
    echo '<h3>' . get_string('gradinglogs', 'local_llmgrader') . '</h3>';

    $attempts = $DB->get_records_sql("
        SELECT DISTINCT qa.id, qa.quiz, qa.userid, qa.timefinish, c.shortname as coursetype
          FROM {quiz_attempts} qa
          INNER JOIN {quiz} q ON q.id = qa.quiz
          WHERE q.course = ?", [$courseid], 0, 100);

    if ($attempts) {
        $attemptids = array_keys($attempts);
        list($insql, $inparams) = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED);
        $logs = $DB->get_records_select("
            SELECT ll.*, qa.userid, q.name as quizname
              FROM {local_llmgrader_log} ll
              INNER JOIN {quiz_attempts} qa ON ll.attemptid = qa.id
              INNER JOIN {quiz} q ON qa.quiz = q.id
             WHERE qa.quiz IN (SELECT id FROM {quiz} WHERE course = ?)
          ORDER BY ll.timecreated DESC", [$courseid], 0, 100);

        if ($logs) {
            $table = new \html_table();
            $table->head = [
                get_string('quiz'),
                get_string('user'),
                get_string('slot'),
                get_string('score'),
                get_string('status'),
                get_string('time'),
            ];
            $table->align = ['left', 'left', 'center', 'right', 'center', 'center'];

            foreach ($logs as $log) {
                $quizname = $log->quizname ?? get_string('unknown');
                $user = $DB->get_record('user', ['id' => $log->userid], 'username');
                $username = $user ? $user->username : '-';

                $statuslabel = get_string('status' . $log->status, 'local_llmgrader');

                $table->data[] = [
                    format_string($quizname),
                    $username,
                    $log->slot,
                    isset($log->score) ? number_format($log->score, 2) : '-',
                    $statuslabel,
                    userdate($log->timecompleted ?? $log->timecreated),
                ];
            }

            echo html_writer::table($table);
        } else {
            echo '<p>' . get_string('nogradesfound', 'local_llmgrader') . '</p>';
        }
    } else {
        echo '<p>' . get_string('nogradesfound', 'local_llmgrader') . '</p>';
    }

    echo $OUTPUT->footer();
    exit;
}

// --- Configuration form handling ---
$config = course_config_manager::get($courseid);
$formdata = null;

if ($save && confirm_sesskey()) {
    if ($hasconfigure) {
        $mform = new course_config_form($url, ['course' => $course]);
        if ($data = $mform->get_data()) {
            $configdata = course_config_manager::from_form_data($data);
            course_config_manager::update($courseid, $configdata);
            redirect($url, get_string('configsaved', 'local_llmgrader'), 0);
        }
    } else {
        // No permission to save.
        echo '<div class="alert alert-warning">' . get_string('nopermissions', 'local_llmgrader') . '</div>';
    }
}

if ($reset && confirm_sesskey() && $hasconfigure) {
    course_config_manager::delete($courseid);
    redirect($url, get_string('configreset', 'local_llmgrader'), 0);
}

// Display the form.
$mform = new course_config_form($url, ['course' => $course]);
if ($config && !$save) {
    $mform->set_data($config);
} else if (!$hasconfigure) {
    // Read-only mode: show effective config without editing.
    $effective = config_provider::get_effective_config(0, $courseid);
    $mform->set_data($effective);

    // Disable all form elements for read-only view.
    $elements = $mform->getElements();
    foreach ($elements as $element) {
        if (method_exists($element, 'freeze')) {
            $element->freeze();
        }
    }
}

echo $OUTPUT->box_start('config-form-box');
$mform->display();
echo $OUTPUT->box_end();

// Show inheritance info.
if ($hasview) {
    echo $OUTPUT->box_start();
    echo '<h4>' . get_string('inheritanceinfo', 'local_llmgrader') . '</h4>';
    echo '<dl class="dl-horizontal">';
    echo '<dt>' . get_string('configlevel', 'local_llmgrader') . '</dt>';
    $level = config_provider::get_config_level(0, $courseid);
    $levelstr = get_string('configlevel' . $level, 'local_llmgrader');
    echo '<dd>' . $levelstr . '</dd>';

    // Check which settings are overridden.
    $overridefields = ['enabled', 'api_endpoint', 'model', 'system_prompt', 'feedback_language'];
    foreach ($overridefields as $field) {
        if (config_provider::is_course_overridden($courseid, $field)) {
            echo '<dt>' . get_string('overridden_by_course', 'local_llmgrader') . ': ' . htmlspecialchars($field) . '</dt>';
            echo '<dd><span class="text-success">' . get_string('yes') . '</span></dd>';
        }
    }
    echo '</dl>';
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
