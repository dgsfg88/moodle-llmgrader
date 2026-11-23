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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Manual re-grading page for LLM Grader.
 *
 * Allows teachers to re-run LLM grading on a single question of a quiz attempt,
 * for example when the automatic grading failed or the result is not satisfactory.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$logid = optional_param('logid', 0, PARAM_INT);
$slot = optional_param('slot', 0, PARAM_INT);

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('local/llmgrader:manualregrade', $context);

$PAGE->set_url('/local/llmgrader/retry.php', ['cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manualregrade', 'local_llmgrader'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

// Handle regrade action.
if ($action === 'regrade' && confirm_sesskey()) {
    $log = null;
    if ($logid > 0) {
        $log = $DB->get_record('local_llmgrader_log', ['id' => $logid], '*', IGNORE_MISSING);
    } else if ($attemptid > 0 && $slot > 0) {
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid, 'quiz' => $quiz->id], '*', IGNORE_MISSING);
        if ($attempt) {
            $quba = null;
            try {
                require_once($CFG->dirroot . '/question/engine/lib.php');
                $quba = \question_engine::load_questions_usage_by_activity((int)$attempt->uniqueid);
                $question = $quba->get_question($slot);
                if ($question instanceof \qtype_essay_question) {
                    $log = $DB->get_record('local_llmgrader_log', [
                        'attemptid' => $attemptid,
                        'slot' => $slot,
                        'questionid' => $question->id,
                        'questionversion' => $question->version,
                    ], '*', IGNORE_MISSING);
                }
            } catch (\Throwable $e) {
                // Ignore; handled below.
            }
        }
    }

    if ($log) {
        // Resolve quiz/course ids.
        $attempt = $DB->get_record('quiz_attempts', ['id' => $log->attemptid], '*', MUST_EXIST);
        $quizid = (int)$attempt->quiz;
        $quizrecord = $DB->get_record('quiz', ['id' => $quizid], 'course', MUST_EXIST);
        $courseid = (int)$quizrecord->course;

        // Reset the log for a fresh run.
        $DB->update_record('local_llmgrader_log', (object)[
            'id' => $log->id,
            'status' => 'retry_scheduled',
            'retrycount' => 0,
            'nextretry' => 0,
            'error' => null,
            'lastretryreason' => null,
            'timemodified' => time(),
        ]);

        // Queue the retry task immediately.
        $task = \local_llmgrader\task\retry_grading::instance(
            $log->id,
            $log->attemptid,
            $log->slot,
            $log->questionid,
            $log->questionversion,
            $quizid,
            $courseid,
            true // force overwrite
        );
        \core\task\manager::queue_adhoc_task($task, time());

        redirect(
            new \moodle_url('/local/llmgrader/retry.php', ['cmid' => $cmid, 'attemptid' => $log->attemptid]),
            get_string('regradequeued', 'local_llmgrader'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            new \moodle_url('/local/llmgrader/retry.php', ['cmid' => $cmid, 'attemptid' => $attemptid]),
            get_string('regradefailed', 'local_llmgrader'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// If no attempt selected, list finished attempts for this quiz.
if ($attemptid === 0) {
    echo $OUTPUT->heading(get_string('manualregrade', 'local_llmgrader'));

    $attempts = $DB->get_records('quiz_attempts', ['quiz' => $quiz->id, 'state' => 'finished'], 'timemodified DESC');
    if (empty($attempts)) {
        echo $OUTPUT->notification(get_string('nologs', 'local_llmgrader'), \core\output\notification::NOTIFY_INFO);
    } else {
        $table = new \html_table();
        $table->head = [
            get_string('attempt', 'quiz'),
            get_string('startedon', 'quiz'),
            get_string('grade', 'quiz'),
            '',
        ];
        $table->attributes['class'] = 'generaltable';

        foreach ($attempts as $a) {
            $url = new \moodle_url('/local/llmgrader/retry.php', ['cmid' => $cmid, 'attemptid' => $a->id]);
            $table->data[] = [
                fullname($DB->get_record('user', ['id' => $a->userid])),
                userdate($a->timestart),
                format_float($a->sumgrades, 2),
                \html_writer::link($url, get_string('view'), ['class' => 'btn btn-secondary btn-sm']),
            ];
        }
        echo \html_writer::table($table);
    }

    echo $OUTPUT->footer();
    exit;
}

// Show a single attempt's LLM grading log.
$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid, 'quiz' => $quiz->id], '*', MUST_EXIST);

echo $OUTPUT->heading(get_string('manualregrade', 'local_llmgrader') . ' - ' . get_string('attempt', 'quiz') . ' ' . $attempt->attempt);

$logs = $DB->get_records('local_llmgrader_log', ['attemptid' => $attemptid], 'slot ASC');

if (empty($logs)) {
    echo $OUTPUT->notification(get_string('nologs', 'local_llmgrader'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new \html_table();
    $table->head = [
        get_string('slot', 'quiz'),
        get_string('status', 'local_llmgrader'),
        get_string('score', 'local_llmgrader'),
        get_string('error', 'local_llmgrader'),
        '',
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($logs as $log) {
        $score = ($log->score !== null) ? format_float($log->score, 2) . ' / ' . format_float($log->maxscore, 2) : '-';
        $error = $log->error ? \core_text::substr($log->error, 0, 120) : ($log->lastretryreason ?: '-');

        $form = new \moodle_url('/local/llmgrader/retry.php', [
            'cmid' => $cmid,
            'attemptid' => $attemptid,
            'action' => 'regrade',
            'logid' => $log->id,
            'sesskey' => sesskey(),
        ]);
        $button = \html_writer::link(
            $form,
            get_string('retryquestion', 'local_llmgrader'),
            ['class' => 'btn btn-primary btn-sm']
        );

        $table->data[] = [
            $log->slot,
            $log->status,
            $score,
            $error,
            $button,
        ];
    }
    echo \html_writer::table($table);
}

echo $OUTPUT->footer();
