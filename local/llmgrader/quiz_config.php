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
 * Quiz-level LLM Grader configuration page.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_llmgrader\local\config_provider;
use local_llmgrader\local\quiz_config_manager;
use local_llmgrader\form\quiz_config_form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/local/llmgrader/classes/form/quiz_config_form.php');

$cmid = required_param('cmid', PARAM_INT);
$save = optional_param('save', null, PARAM_RAW);
$reset = optional_param('resettoinherit', 0, PARAM_INT);
$page = optional_param('page', 'config', PARAM_ALPHA);

global $DB;
$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$modcontext = \context_module::instance($cmid);
$coursecontext = \context_course::instance($course->id);

require_login($course, false, $cm);

$hasconfigure = has_capability('local/llmgrader:configurequiz', $modcontext);
$hasview = has_capability('local/llmgrader:viewconfiguration', $coursecontext);

if (!$hasconfigure && !$hasview) {
    require_capability('moodle/course:view', $coursecontext);
    redirect(new moodle_url('/mod/quiz/view.php', ['id' => $cmid]), get_string('nopermissions', 'local_llmgrader'), 0, \core\session\manager::get_session(), \core\session\manager::get_login_url());
}

// Build page URL and navigation.
$url = new moodle_url('/local/llmgrader/quiz_config.php', ['cmid' => $cmid]);
$PAGE->set_url($url);
$PAGE->set_context($modcontext);
$PAGE->set_title(format_string($quiz->name) . ': ' . get_string('quizconfigheading', 'local_llmgrader'));
$PAGE->set_heading(format_string($quiz->name));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('quizconfigheading', 'local_llmgrader'));

// Navigation tabs.
echo '<div class="tabtree"><ul class="nav nav-tabs">';
$configurl = new moodle_url('/local/llmgrader/quiz_config.php', ['cmid' => $cmid, 'page' => 'config']);
$logurl = new moodle_url('/local/llmgrader/quiz_config.php', ['cmid' => $cmid, 'page' => 'logs']);

echo '<li class="nav-item"><a class="nav-link ' . ($page === 'config' ? 'active' : '') . '" href="' . $configurl->out() . '">' .
    get_string('settings', 'local_llmgrader') . '</a></li>';
echo '<li class="nav-item"><a class="nav-link ' . ($page === 'logs' ? 'active' : '') . '" href="' . $logurl->out() . '">' .
    get_string('gradinglogs', 'local_llmgrader') . '</a></li>';
echo '</ul></div>';

if ($page === 'logs') {
    // Show grading logs for this quiz.
    echo '<h3>' . get_string('gradinglogs', 'local_llmgrader') . '</h3>';

    $attempts = $DB->get_records('quiz_attempts', ['quiz' => $quiz->id, 'state' => 'submitted'], 'timefinish DESC', 'id, userid, timefinish', 0, 100);

    if ($attempts) {
        $attemptids = array_keys($attempts);
        list($insql, $inparams) = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED);
        $logs = $DB->get_records_select("
            SELECT ll.*, qa.userid
              FROM {local_llmgrader_log} ll
              INNER JOIN {quiz_attempts} qa ON ll.attemptid = qa.id
             WHERE qa.quiz = ? AND ll.attemptid IN ($insql)
          ORDER BY ll.timecreated DESC", array_merge([$quiz->id], $inparams), 0, 100);

        if ($logs) {
            $table = new \html_table();
            $table->head = [
                get_string('attempt'),
                get_string('user'),
                get_string('slot'),
                get_string('score'),
                get_string('status'),
                get_string('time'),
            ];
            $table->align = ['left', 'left', 'center', 'right', 'center', 'center'];

            foreach ($logs as $log) {
                $user = $DB->get_record('user', ['id' => $log->userid], 'username');
                $username = $user ? $user->username : '-';

                $statuslabel = get_string('status' . $log->status, 'local_llmgrader');

                $table->data[] = [
                    $log->attemptid,
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
$config = quiz_config_manager::get($quiz->id);
$formdata = null;

if ($save && confirm_sesskey()) {
    if ($hasconfigure) {
        // Validate quiz belongs to course.
        if (!quiz_config_manager::validate_course($quiz->id, $course->id)) {
            redirect($url, get_string('error_quiz_mismatch', 'local_llmgrader'), 0);
        }

        $mform = new quiz_config_form($url, ['quiz' => $quiz, 'course' => $course]);
        if ($data = $mform->get_data()) {
            $configdata = quiz_config_manager::from_form_data($data);
            $configdata->quizid = $quiz->id;
            $configdata->courseid = $course->id;
            quiz_config_manager::update($quiz->id, $configdata);
            redirect($url, get_string('configsaved', 'local_llmgrader'), 0);
        }
    } else {
        echo '<div class="alert alert-warning">' . get_string('nopermissions', 'local_llmgrader') . '</div>';
    }
}

if ($reset && confirm_sesskey() && $hasconfigure) {
    quiz_config_manager::delete($quiz->id);
    redirect($url, get_string('configreset', 'local_llmgrader'), 0);
}

// Display the form.
$mform = new quiz_config_form($url, ['quiz' => $quiz, 'course' => $course]);
if ($config && !$save) {
    $mform->set_data($config);
} else if (!$hasconfigure) {
    // Read-only mode: show effective config without editing.
    $effective = config_provider::get_effective_config($quiz->id, $course->id);
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
    $level = config_provider::get_config_level($quiz->id, $course->id);
    $levelstr = get_string('configlevel' . $level, 'local_llmgrader');
    echo '<dd>' . $levelstr . '</dd>';

    // Check which settings are overridden at each level.
    $overridefields = ['enabled', 'api_endpoint', 'model', 'system_prompt', 'feedback_language'];
    foreach ($overridefields as $field) {
        if (config_provider::is_quiz_overridden($quiz->id, $field)) {
            echo '<dt>' . get_string('overridden_by_quiz', 'local_llmgrader') . ': ' . htmlspecialchars($field) . '</dt>';
            echo '<dd><span class="text-success">' . get_string('yes') . '</span></dd>';
        } else if (config_provider::is_course_overridden($course->id, $field)) {
            echo '<dt>' . get_string('overridden_by_course', 'local_llmgrader') . ': ' . htmlspecialchars($field) . '</dt>';
            echo '<dd><span class="text-warning">' . get_string('yes') . '</span></dd>';
        }
    }
    echo '</dl>';
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
