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
 * Ad hoc task to retry grading for failed essay questions.
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
     * @return self
     */
    public static function instance(int $logid, int $attemptid, int $slot, int $questionid, int $questionversion): self {
        $task = new self();
        $task->set_custom_data((object)[
            'logid'           => $logid,
            'attemptid'       => $attemptid,
            'slot'            => $slot,
            'questionid'      => $questionid,
            'questionversion' => $questionversion,
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
        global $DB;

        $data = $this->get_custom_data();

        // Verify the log record.
        $log = $DB->get_record('local_llmgrader_log', ['id' => $data->logid], '*', MUST_EXIST);

        if ($log->status !== 'retry_scheduled') {
            mtrace("Log {$data->logid} is not in retry_scheduled state ({$log->status}). Skipping.");
            return;
        }

        // Check max retries.
        $maxretries = get_config('local_llmgrader', 'max_retries') ?: 8;
        if ($log->retrycount >= $maxretries) {
            $DB->set_field('local_llmgrader_log', 'status', 'failed', ['id' => $log->id]);
            mtrace("Log {$data->logid} exceeded max retries ({$maxretries}). Marking as failed.");
            return;
        }

        // Load the question usage.
        try {
            $quba = \question_engine::load_questions_usage_by_activity($data->attemptid);
        } catch (exception $e) {
            mtrace("Failed to load question usage for retry: " . $e->getMessage());
            return;
        }

        // Get the question.
        $question = $quba->get_question($data->slot);
        if (!($question instanceof \qtype_essay_question)) {
            mtrace("Slot {$data->slot} is not an essay question. Skipping retry.");
            return;
        }

        // Grade the essay.
        try {
            $essaygrader = new essay_grader();
            $result = $essaygrader->grade($question, $quba, $data->slot, $data->attemptid);

            if ($result) {
                $DB->set_field('local_llmgrader_log', 'status', 'completed', ['id' => $log->id]);
                $DB->set_field('local_llmgrader_log', 'score', $result['score'], ['id' => $log->id]);
                $DB->set_field('local_llmgrader_log', 'maxscore', $result['maxscore'], ['id' => $log->id]);
                $DB->set_field('local_llmgrader_log', 'feedback', $result['feedback'] ?? '', ['id' => $log->id]);
                $DB->set_field('local_llmgrader_log', 'model', $result['model'] ?? '', ['id' => $log->id]);
                $DB->set_field('local_llmgrader_log', 'timecompleted', time(), ['id' => $log->id]);
                mtrace("Retry successful for log {$data->logid}: {$result['score']}/{$result['maxscore']}");
            }
        } catch (exception $e) {
            $retrymanager = new \local_llmgrader\local\retry_manager();
            if ($retrymanager->is_retryable($e) && $log->retrycount + 1 < $maxretries) {
                $delay = $retrymanager->get_delay($log->retrycount);
                $DB->set_field('local_llmgrader_log', 'nextretry', time() + $delay, ['id' => $log->id]);
                $DB->set_field('local_llmgrader_log', 'retrycount', $log->retrycount + 1, ['id' => $log->id]);
                $DB->set_field('local_llmgrader_log', 'lastretryreason', substr($e->getMessage(), 0, 500), ['id' => $log->id]);

                $retrytask = self::instance(
                    $log->id,
                    $data->attemptid,
                    $data->slot,
                    $data->questionid,
                    $data->questionversion
                );
                \core\task\manager::queue_adhoc_task($retrytask);
                $retrynum = $log->retrycount + 1;
                mtrace("Retry {$retrynum} failed for log {$data->logid}, will retry in {$delay}s.");
            } else {
                $DB->set_field('local_llmgrader_log', 'status', 'failed', ['id' => $log->id]);
                $DB->set_field('local_llmgrader_log', 'error', substr($e->getMessage(), 0, 1000), ['id' => $log->id]);
                $retrynum = $log->retrycount + 1;
                mtrace("Retry {$retrynum} failed permanently for log {$data->logid}: " . $e->getMessage());
            }
        }
    }
}
