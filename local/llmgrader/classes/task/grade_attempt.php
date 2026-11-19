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
 * Ad hoc task to grade all eligible questions in a quiz attempt.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\task;

use core\task\adhoc_task;
use exception;
use local_llmgrader\local\essay_grader;
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
     * @return self
     */
    public static function instance(int $attemptid, int $quizid): self {
        $task = new self();
        $task->set_custom_data((object)[
            'attemptid' => $attemptid,
            'quizid'    => $quizid,
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
        global $DB;

        $data = $this->get_custom_data();
        if (!isset($data->attemptid, $data->quizid)) {
            mtrace('Invalid custom data for grade_attempt task.');
            return;
        }

        $attemptid = (int)$data->attemptid;
        $quizid = (int)$data->quizid;

        // Verify the attempt exists and is submitted.
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid, 'quiz' => $quizid], '*', MUST_EXIST);
        if ($attempt->state !== 'submitted') {
            mtrace("Attempt {$attemptid} is not in submitted state (state: {$attempt->state}). Skipping.");
            return;
        }

        // Load the question usage.
        try {
            $quba = \question_engine::load_questions_usage_by_activity($attempt->quizusage);
        } catch (exception $e) {
            mtrace("Failed to load question usage for attempt {$attemptid}: " . $e->getMessage());
            return;
        }

        // Get essay questions that need grading.
        $essaygrader = new essay_grader();
        $slots = $quba->get_slots();

        foreach ($slots as $slot) {
            $question = $quba->get_question($slot);

            // Only process Essay questions.
            if (!($question instanceof \qtype_essay_question)) {
                continue;
            }

            // Check if graderinfo is set (eligibility criterion).
            if (empty($question->graderinfo)) {
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

            // Create or retrieve the grading log record.
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
                ];
                $existinglog->id = $DB->insert_record('local_llmgrader_log', $existinglog);
            }

            // Grade the essay.
            try {
                $result = $essaygrader->grade($question, $quba, $slot, $attemptid);

                if ($result) {
                    $DB->set_field('local_llmgrader_log', 'status', 'completed', [
                        'id' => $existinglog->id,
                    ]);
                    $DB->set_field('local_llmgrader_log', 'score', $result['score'], [
                        'id' => $existinglog->id,
                    ]);
                    $DB->set_field('local_llmgrader_log', 'maxscore', $result['maxscore'], [
                        'id' => $existinglog->id,
                    ]);
                    $DB->set_field('local_llmgrader_log', 'feedback', $result['feedback'] ?? '', [
                        'id' => $existinglog->id,
                    ]);
                    $DB->set_field('local_llmgrader_log', 'model', $result['model'] ?? '', [
                        'id' => $existinglog->id,
                    ]);
                    $DB->set_field('local_llmgrader_log', 'timecompleted', time(), [
                        'id' => $existinglog->id,
                    ]);
                    mtrace("Slot {$slot} graded: {$result['score']}/{$result['maxscore']}");
                }
            } catch (exception $e) {
                // Handle retry logic.
                $retrymanager = new \local_llmgrader\local\retry_manager();
                if ($retrymanager->is_retryable($e)) {
                    $delay = $retrymanager->get_delay($existinglog->retrycount);
                    $DB->set_field('local_llmgrader_log', 'status', 'retry_scheduled', [
                        'id' => $existinglog->id,
                    ]);
                    $DB->set_field('local_llmgrader_log', 'nextretry', time() + $delay, [
                        'id' => $existinglog->id,
                    ]);
                    $DB->set_field('local_llmgrader_log', 'retrycount', $existinglog->retrycount + 1, [
                        'id' => $existinglog->id,
                    ]);
                    $DB->set_field('local_llmgrader_log', 'lastretryreason', substr($e->getMessage(), 0, 500), [
                        'id' => $existinglog->id,
                    ]);

                    // Queue a retry task.
                    $retrytask = retry_grading::instance(
                        $existinglog->id,
                        $attemptid,
                        $slot,
                        $question->id,
                        $question->version
                    );
                    manager::queue_adhoc_task($retrytask);
                    mtrace("Slot {$slot} failed (will retry in {$delay}s): " . $e->getMessage());
                } else {
                    $DB->set_field('local_llmgrader_log', 'status', 'failed', [
                        'id' => $existinglog->id,
                    ]);
                    $DB->set_field('local_llmgrader_log', 'error', substr($e->getMessage(), 0, 1000), [
                        'id' => $existinglog->id,
                    ]);
                    mtrace("Slot {$slot} failed permanently: " . $e->getMessage());
                }
            }
        }

        mtrace("Grade attempt task completed for attempt {$attemptid}.");
    }
}
