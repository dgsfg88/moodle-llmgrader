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
 * Ad hoc task to retry grading for failed essay questions.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\task;

use core\task\adhoc_task;
use local_llmgrader\local\config_provider;
use local_llmgrader\local\essay_grader;
use local_llmgrader\local\retry_manager;
use mod_quiz\quiz_attempt;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Ad hoc task to retry grading a specific essay question.
 */
class retry_grading extends adhoc_task {

    /**
     * Create a new instance.
     *
     * @param int $logid Grading log ID
     * @param int $attemptid Quiz attempt ID
     * @param int $slot Question slot
     * @param int $questionid Question ID
     * @param int $questionversion Question version
     * @param int $quizid Quiz ID (for config resolution)
     * @param int $courseid Course ID (for config resolution)
     * @param bool $force Whether to overwrite an existing teacher/LLM grade.
     * @return self
     */
    public static function instance(int $logid, int $attemptid, int $slot, int $questionid, int $questionversion, int $quizid = 0, int $courseid = 0, bool $force = false): self {
        $task = new self();
        $task->set_custom_data((object)[
            'logid'           => $logid,
            'attemptid'       => $attemptid,
            'slot'            => $slot,
            'questionid'      => $questionid,
            'questionversion' => $questionversion,
            'quizid'          => $quizid,
            'courseid'        => $courseid,
            'force'           => $force,
        ]);
        return $task;
    }

    /**
     * Execute the retry grading task.
     *
     * @return void
     * @throws moodle_exception
     */
    public function execute(): void {
        global $CFG, $DB;

        // The question engine classes are not autoloaded in Moodle 5.0.
        require_once($CFG->dirroot . '/question/engine/lib.php');

        $data = $this->get_custom_data();

        // Verify the log record.
        $log = $DB->get_record('local_llmgrader_log', ['id' => $data->logid], '*', MUST_EXIST);

        if ($log->status !== 'retry_scheduled') {
            mtrace("Log {$data->logid} is not in retry_scheduled state ({$log->status}). Skipping.");
            return;
        }

        // Resolve quiz and course IDs for config lookup.
        $quizid = isset($data->quizid) ? (int)$data->quizid : 0;
        $courseid = isset($data->courseid) ? (int)$data->courseid : 0;

        // Load the attempt: it gives us both the quiz id and the QUBA id.
        $attempt = $DB->get_record('quiz_attempts', ['id' => $data->attemptid], '*', IGNORE_MISSING);
        if (!$attempt) {
            mtrace("Attempt {$data->attemptid} not found. Skipping retry.");
            return;
        }

        if ($quizid === 0) {
            $quizid = (int)$attempt->quiz;
            $quiz = $DB->get_record('quiz', ['id' => $quizid], 'course', IGNORE_MISSING);
            if ($quiz) {
                $courseid = (int)$quiz->course;
            }
        }

        if ($attempt->state !== quiz_attempt::FINISHED) {
            mtrace("Attempt {$data->attemptid} is not finished (state: {$attempt->state}). Skipping retry.");
            return;
        }

        // Resolve the effective configuration for this quiz/course context.
        $config = config_provider::get_effective_config($quizid, $courseid);

        // Check max retries using config-specific setting.
        $maxretries = (int)($config->max_retries ?? 8);
        $retrycount = (int)$log->retrycount;
        if ($retrycount >= $maxretries) {
            $DB->set_field('local_llmgrader_log', 'status', 'failed', ['id' => $log->id]);
            mtrace("Log {$data->logid} exceeded max retries ({$maxretries}). Marking as failed.");
            return;
        }

        // Load the question usage. quiz_attempts.uniqueid is the QUBA id.
        try {
            $quba = \question_engine::load_questions_usage_by_activity((int)$attempt->uniqueid);
        } catch (\Throwable $e) {
            mtrace("Failed to load question usage for retry: " . $e->getMessage());
            return;
        }

        // Get the question.
        $question = $quba->get_question($data->slot);
        if (!($question instanceof \qtype_essay_question)) {
            mtrace("Slot {$data->slot} is not an essay question. Skipping retry.");
            return;
        }

        // Never overwrite a teacher grade, unless this is a forced manual retry.
        $force = !empty($data->force);
        $qa = $quba->get_question_attempt($data->slot);
        if ($qa->get_state()->is_graded() && !$force) {
            $DB->set_field('local_llmgrader_log', 'status', 'completed', ['id' => $log->id]);
            mtrace("Slot {$data->slot} is already graded. Marking retry as completed.");
            return;
        }

        // Grade the essay with contextual config.
        try {
            $essaygrader = new essay_grader($config);
            $result = $essaygrader->grade($question, $quba, $data->slot, $data->attemptid, $quizid, $courseid);

            $DB->update_record('local_llmgrader_log', (object)[
                'id' => $log->id,
                'status' => 'completed',
                'score' => $result['score'],
                'maxscore' => $result['maxscore'],
                'feedback' => $result['feedback'] ?? '',
                'model' => $result['model'] ?? '',
                'lasthttpcode' => $essaygrader->get_last_http_code(),
                'timecompleted' => time(),
                'timemodified' => time(),
            ]);
            mtrace("Retry successful for log {$data->logid}: {$result['score']}/{$result['maxscore']}");
        } catch (\Throwable $e) {
            $retrymanager = new retry_manager($config);
            if ($retrymanager->is_retryable($e) && $retrycount + 1 < $maxretries) {
                $delay = $retrymanager->get_delay($retrycount);
                $DB->update_record('local_llmgrader_log', (object)[
                    'id' => $log->id,
                    'status' => 'retry_scheduled',
                    'nextretry' => time() + $delay,
                    'retrycount' => $retrycount + 1,
                    'lastretryreason' => substr($e->getMessage(), 0, 500),
                    'lasthttpcode' => $essaygrader->get_last_http_code(),
                    'timemodified' => time(),
                ]);

                $retrytask = self::instance(
                    $log->id,
                    $data->attemptid,
                    $data->slot,
                    $data->questionid,
                    $data->questionversion,
                    $quizid,
                    $courseid
                );
                \core\task\manager::queue_adhoc_task($retrytask, time() + $delay);
                $retrynum = $retrycount + 1;
                mtrace("Retry {$retrynum} failed for log {$data->logid}, will retry in {$delay}s.");
            } else {
                $DB->update_record('local_llmgrader_log', (object)[
                    'id' => $log->id,
                    'status' => 'failed',
                    'error' => substr($e->getMessage(), 0, 1000),
                    'lasthttpcode' => $essaygrader->get_last_http_code(),
                    'timemodified' => time(),
                ]);
                $retrynum = $retrycount + 1;
                mtrace("Retry {$retrynum} failed permanently for log {$data->logid}: " . $e->getMessage());
            }
        }
    }
}
