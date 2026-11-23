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
 * Ad hoc task to grade all eligible questions in a quiz attempt.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\task;

use core\task\adhoc_task;
use core\task\manager;
use local_llmgrader\local\config_provider;
use local_llmgrader\local\essay_grader;
use local_llmgrader\local\retry_manager;
use mod_quiz\quiz_attempt;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Ad hoc task to grade a quiz attempt.
 */
class grade_attempt extends adhoc_task {

    /**
     * Create a new instance for the given attempt.
     *
     * @param int $attemptid Quiz attempt ID
     * @param int $quizid Quiz ID
     * @param int $courseid Course ID
     * @return self
     */
    public static function instance(int $attemptid, int $quizid, int $courseid = 0): self {
        $task = new self();
        $task->set_custom_data((object)[
            'attemptid' => $attemptid,
            'quizid'    => $quizid,
            'courseid'  => $courseid,
        ]);
        return $task;
    }

    /**
     * Execute the grading task.
     *
     * @return void
     * @throws moodle_exception
     */
    public function execute(): void {
        global $CFG, $DB;

        // The question engine classes are not autoloaded in Moodle 5.0.
        require_once($CFG->dirroot . '/question/engine/lib.php');

        $data = $this->get_custom_data();
        if (!isset($data->attemptid, $data->quizid)) {
            mtrace('Invalid custom data for grade_attempt task.');
            return;
        }

        $attemptid = (int)$data->attemptid;
        $quizid = (int)$data->quizid;
        $courseid = isset($data->courseid) ? (int)$data->courseid : 0;

        // Resolve the effective configuration for this quiz/course context.
        $config = config_provider::get_effective_config($quizid, $courseid);

        // Verify the attempt exists and is finished.
        // Moodle stores submitted attempts with state "finished", not "submitted".
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid, 'quiz' => $quizid], '*', IGNORE_MISSING);
        if (!$attempt) {
            mtrace("Attempt {$attemptid} not found for quiz {$quizid}. Skipping.");
            return;
        }
        if ($attempt->state !== quiz_attempt::FINISHED) {
            mtrace("Attempt {$attemptid} is not finished (state: {$attempt->state}). Skipping.");
            return;
        }
        if (!empty($attempt->preview)) {
            mtrace("Attempt {$attemptid} is a preview attempt. Skipping.");
            return;
        }

        // Load the question usage. In quiz_attempts the question usage id is
        // stored in the "uniqueid" column (there is no "quizusage" column).
        try {
            $quba = \question_engine::load_questions_usage_by_activity((int)$attempt->uniqueid);
        } catch (\Throwable $e) {
            mtrace("Failed to load question usage for attempt {$attemptid}: " . $e->getMessage());
            return;
        }

        // Get essay questions that need grading.
        $essaygrader = new essay_grader($config);
        $slots = $quba->get_slots();

        foreach ($slots as $slot) {
            $question = $quba->get_question($slot);

            // Only process Essay questions.
            if (!($question instanceof \qtype_essay_question)) {
                continue;
            }

            // Never overwrite a grade already assigned by a teacher.
            $qa = $quba->get_question_attempt($slot);
            if ($qa->get_state()->is_graded()) {
                mtrace("Slot {$slot} is already graded. Skipping.");
                continue;
            }

            // Check if already graded automatically.
            $existing = $DB->get_record('local_llmgrader_log', [
                'attemptid' => $attemptid,
                'slot' => $slot,
                'questionid' => $question->id,
                'questionversion' => $question->version,
                'status' => 'completed',
            ]);
            if ($existing) {
                mtrace("Slot {$slot} already graded automatically. Skipping.");
                continue;
            }

            // Check for existing grading record (any status).
            $existinglog = $DB->get_record('local_llmgrader_log', [
                'attemptid' => $attemptid,
                'slot' => $slot,
                'questionid' => $question->id,
                'questionversion' => $question->version,
            ]);

            // Create or reset the grading log record.
            if (!$existinglog) {
                $existinglog = (object)[
                    'attemptid' => $attemptid,
                    'slot' => $slot,
                    'questionid' => $question->id,
                    'questionversion' => $question->version,
                    'status' => 'pending',
                    'retrycount' => 0,
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'configlevel' => $config->configlevel,
                    'configid' => $config->configid,
                ];
                $existinglog->id = $DB->insert_record('local_llmgrader_log', $existinglog);
            } else {
                $existinglog->retrycount = (int)$existinglog->retrycount;
                $DB->update_record('local_llmgrader_log', (object)[
                    'id' => $existinglog->id,
                    'status' => 'pending',
                    'configlevel' => $config->configlevel,
                    'configid' => $config->configid,
                    'timemodified' => time(),
                ]);
                $existinglog->status = 'pending';
            }

            // Grade the essay.
            try {
                $result = $essaygrader->grade($question, $quba, $slot, $attemptid, $quizid, $courseid);

                $DB->update_record('local_llmgrader_log', (object)[
                    'id' => $existinglog->id,
                    'status' => 'completed',
                    'score' => $result['score'],
                    'maxscore' => $result['maxscore'],
                    'feedback' => $result['feedback'] ?? '',
                    'model' => $result['model'] ?? '',
                    'lasthttpcode' => $essaygrader->get_last_http_code(),
                    'timecompleted' => time(),
                    'timemodified' => time(),
                ]);
                mtrace("Slot {$slot} graded: {$result['score']}/{$result['maxscore']} (config: {$config->configlevel})");
            } catch (\Throwable $e) {
                // Handle retry logic using config-specific settings.
                $retrymanager = new retry_manager($config);
                $retrycount = (int)$existinglog->retrycount;
                $maxretries = (int)$config->max_retries;

                if ($retrymanager->is_retryable($e) && $retrycount < $maxretries) {
                    $delay = $retrymanager->get_delay($retrycount);
                    $DB->update_record('local_llmgrader_log', (object)[
                        'id' => $existinglog->id,
                        'status' => 'retry_scheduled',
                        'nextretry' => time() + $delay,
                        'retrycount' => $retrycount + 1,
                        'lastretryreason' => substr($e->getMessage(), 0, 500),
                        'lasthttpcode' => $essaygrader->get_last_http_code(),
                        'timemodified' => time(),
                    ]);

                    // Queue a retry task at the scheduled time.
                    $retrytask = retry_grading::instance(
                        $existinglog->id,
                        $attemptid,
                        $slot,
                        $question->id,
                        $question->version,
                        $quizid,
                        $courseid
                    );
                    manager::queue_adhoc_task($retrytask, time() + $delay);
                    mtrace("Slot {$slot} failed (will retry in {$delay}s): " . $e->getMessage());
                } else {
                    $DB->update_record('local_llmgrader_log', (object)[
                        'id' => $existinglog->id,
                        'status' => 'failed',
                        'error' => substr($e->getMessage(), 0, 1000),
                        'lasthttpcode' => $essaygrader->get_last_http_code(),
                        'timemodified' => time(),
                    ]);
                    mtrace("Slot {$slot} failed permanently: " . $e->getMessage());
                }
            }
        }

        mtrace("Grade attempt task completed for attempt {$attemptid}.");
    }
}
