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
 * Essay grader - orchestrates LLM-based grading of essay questions.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\local;

use question_attempt;
use question_definition;
use question_usage_by_activity;

// MOODLE_INTERNAL check removed - class is autoloaded

/**
 * Essay grader class.
 *
 * Orchestrates the complete grading operation for a single essay question:
 * 1. Retrieves student response and question data.
 * 2. Converts content to Markdown.
 * 3. Builds the LLM prompt.
 * 4. Sends request to LLM provider.
 * 5. Parses and validates response.
 * 6. Records grade via Question Engine API.
 */
class essay_grader {

    /** @var content_converter */
    protected content_converter $converter;

    /** @var llm_client */
    protected llm_client $client;

    /** @var response_parser */
    protected response_parser $parser;

    /** @var object|null Effective configuration for this grading operation */
    protected ?object $config = null;

    /**
     * Constructor.
     *
     * @param object|null $config Optional effective configuration. If null, global config is used.
     */
    public function __construct(?object $config = null) {
        $this->converter = new content_converter();
        $this->client = new llm_client($config);
        $this->parser = new response_parser();
        $this->config = $config;
    }

    /**
     * Grade a single essay question.
     *
     * @param \qtype_essay_question $question The essay question definition.
     * @param \question_usage_by_activity $quba The question usage by activity.
     * @param int $slot The question slot number.
     * @param int $attemptid The quiz attempt ID.
     * @param int $quizid The quiz ID (for config resolution).
     * @param int $courseid The course ID (for config resolution).
     * @return array Grading result with score, maxscore, feedback, and model.
     * @throws \Exception On grading failure.
     */
    public function grade(\qtype_essay_question $question, question_usage_by_activity $quba, int $slot, int $attemptid, int $quizid = 0, int $courseid = 0): array {
        // Get student response and the mark actually assigned to this slot.
        // The maximum mark lives on the question attempt, not on the question
        // definition ($question->maxmark does not exist).
        $qa = $quba->get_question_attempt($slot);
        $response = $this->get_student_response($qa);
        $maxmark = (float)$quba->get_question_max_mark($slot);

        // Build grading context with resolved config.
        $gradingcontext = $this->build_grading_context(
            $question,
            $response,
            $quba,
            $attemptid,
            $quizid,
            $courseid,
            $maxmark
        );

        // Send to LLM and get result.
        $llmresult = $this->client->send_request($gradingcontext);

        // Parse and validate response.
        $parsed = $this->parser->parse_and_validate(
            $llmresult,
            $maxmark,
            (string)$gradingcontext['feedbacklanguage']
        );

        // Convert feedback Markdown to safe HTML.
        $feedbackhtml = $this->converter->markdown_to_html_safe($parsed['comment']);

        // Apply grade via Question Engine API.
        $this->apply_grade($quba, $slot, $attemptid, $quizid, $feedbackhtml, (float)$parsed['score']);

        return [
            'score' => $parsed['score'],
            'maxscore' => $maxmark,
            'feedback' => $feedbackhtml,
            'model' => $this->client->get_model(),
        ];
    }

    /**
     * Retrieve the student's essay response.
     *
     * @param \question_attempt $qa The question attempt.
     * @return array ['text' => string, 'format' => int]
     */
    protected function get_student_response(question_attempt $qa): array {
        // Read the last response variables explicitly: get_last_qt_data() may
        // also contain grading step data, so do not rely on it here.
        $answer = $qa->get_last_qt_var('answer');
        if ($answer === null) {
            $answer = '';
        }

        $answerformat = $qa->get_last_qt_var('answerformat');
        if ($answerformat === null) {
            $answerformat = FORMAT_HTML;
        }

        return [
            'text' => (string)$answer,
            'format' => (int)$answerformat,
        ];
    }

    /**
     * Build the grading context with all necessary information.
     *
     * @param \qtype_essay_question $question
     * @param array $response
     * @param \question_usage_by_activity $quba
     * @param int $attemptid
     * @param int $quizid
     * @param int $courseid
     * @param float $maxmark Maximum mark for this question slot.
     * @return array The grading context for LLM prompt building.
     */
    protected function build_grading_context(\qtype_essay_question $question, array $response, question_usage_by_activity $quba, int $attemptid, int $quizid = 0, int $courseid = 0, float $maxmark = 0.0): array {
        // Use provided config or resolve from context.
        $config = $this->config;
        if (!$config && ($quizid > 0 || $courseid > 0)) {
            $config = config_provider::get_effective_config($quizid, $courseid);
        }

        // Fall back to global config if still no config available.
        if (!$config) {
            $config = config_provider::get_global_config();
        }

        $questiontextformat = (int)($question->questiontextformat ?? FORMAT_HTML);

        return [
            'question' => [
                'text' => $question->name,
                'textformat' => FORMAT_PLAIN,
            ],
            'questiontext' => $this->converter->html_to_markdown($question->questiontext ?? '', $questiontextformat),
            'questiontextformat' => $questiontextformat,
            'graderinfo' => $question->graderinfo ?? '',
            'graderinfoformat' => (int)($question->graderinfoformat ?? FORMAT_HTML),
            'studentresponse' => $response['text'],
            'studentresponseformat' => $response['format'],
            'maxmark' => $maxmark,
            'feedbacklanguage' => $config->feedback_language ?: 'it',
            'systemprompt' => $config->system_prompt ?: '',
        ];
    }

    /**
     * Apply the grade to the question attempt via Moodle Question Engine API.
     *
     * @param \question_usage_by_activity $quba
     * @param int $slot
     * @param int $attemptid
     * @param int $quizid
     * @param string $feedback
     * @param float $score Absolute mark (not a fraction).
     */
    protected function apply_grade(question_usage_by_activity $quba, int $slot, int $attemptid, int $quizid, string $feedback, float $score): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/question/engine/lib.php');

        // question_attempt::manual_grade() expects the absolute mark, not a
        // fraction of the maximum mark.
        $quba->manual_grade($slot, $feedback, $score, FORMAT_HTML);
        \question_engine::save_questions_usage_by_activity($quba);

        // Update the attempt total and the gradebook. Without this the question
        // would be graded but the quiz attempt would still show no grade.
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'id,quiz,userid', IGNORE_MISSING);
        if (!$attempt) {
            return;
        }

        // get_total_mark() returns null while other questions in the attempt
        // are still awaiting grading: in that case keep the previous value.
        $totalmark = $quba->get_total_mark();
        if ($totalmark !== null) {
            $DB->set_field('quiz_attempts', 'sumgrades', $totalmark, ['id' => $attemptid]);
        }
        $DB->set_field('quiz_attempts', 'timemodified', time(), ['id' => $attemptid]);

        try {
            $quizobj = \mod_quiz\quiz_settings::create((int)$attempt->quiz, (int)$attempt->userid);
            $quizobj->get_grade_calculator()->recompute_final_grade((int)$attempt->userid);
        } catch (\Throwable $e) {
            // The question grade is already saved; a gradebook recalculation
            // failure must not discard it.
            debugging('local_llmgrader: could not recompute quiz grade: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Get the HTTP status code of the last LLM request.
     *
     * @return int|null
     */
    public function get_last_http_code(): ?int {
        return $this->client->get_last_http_code();
    }
}
