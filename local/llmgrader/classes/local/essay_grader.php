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

defined('MOODLE_INTERNAL') || die();

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

    /**
     * Constructor.
     */
    public function __construct() {
        $this->converter = new content_converter();
        $this->client = new llm_client();
        $this->parser = new response_parser();
    }

    /**
     * Grade a single essay question.
     *
     * @param \qtype_essay_question $question The essay question definition.
     * @param \question_usage_by_activity $quba The question usage by activity.
     * @param int $slot The question slot number.
     * @param int $attemptid The quiz attempt ID.
     * @return array Grading result with score, maxscore, feedback, and model.
     * @throws \Exception On grading failure.
     */
    public function grade(\qtype_essay_question $question, question_usage_by_activity $quba, int $slot, int $attemptid): array {
        // Get student response.
        $qa = $quba->get_question_attempt($slot);
        $response = $this->get_student_response($qa);

        // Build grading context.
        $gradingcontext = $this->build_grading_context($question, $response, $quba, $attemptid);

        // Send to LLM and get result.
        $llmresult = $this->client->send_request($gradingcontext);

        // Parse and validate response.
        $parsed = $this->parser->parse_and_validate($llmresult, (float)$question->maxmark);

        // Convert feedback Markdown to safe HTML.
        $feedbackhtml = $this->converter->markdown_to_html_safe($parsed['comment']);

        // Apply grade via Question Engine API.
        $this->apply_grade($quba, $slot, $feedbackhtml, $parsed['score'], (float)$question->maxmark);

        return [
            'score' => $parsed['score'],
            'maxscore' => (float)$question->maxmark,
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
        $data = $qa->get_last_qt_data();
        $answer = $data['answer'] ?? '';
        $answerformat = $data['answerformat'] ?? FORMAT_HTML;

        return [
            'text' => $answer,
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
     * @return array The grading context for LLM prompt building.
     */
    protected function build_grading_context(\qtype_essay_question $question, array $response, question_usage_by_activity $quba, int $attemptid): array {
        // Get course context.
        $context = \context_module::instance($quba->get_context()->instanceid);
        $coursecontext = $context->get_course_context(false);

        return [
            'question' => [
                'text' => $question->name,
                'textformat' => FORMAT_PLAIN,
            ],
            'questiontext' => $this->converter->html_to_markdown($question->questiontext ?? ''),
            'questiontextformat' => $question->qtype->get_question_options_cache($question)->get('questiontextformat') ?? FORMAT_HTML,
            'graderinfo' => $question->graderinfo ?? '',
            'graderinfoformat' => $question->graderinfoformat ?? FORMAT_HTML,
            'studentresponse' => $response['text'],
            'studentresponseformat' => $response['format'],
            'maxmark' => $question->maxmark,
            'feedbacklanguage' => get_config('local_llmgrader', 'feedback_language') ?: 'it',
            'systemprompt' => get_config('local_llmgrader', 'system_prompt') ?: '',
        ];
    }

    /**
     * Apply the grade to the question attempt via Moodle Question Engine API.
     *
     * @param \question_usage_by_activity $quba
     * @param int $slot
     * @param string $feedback
     * @param float $score
     * @param float $maxscore
     */
    protected function apply_grade(question_usage_by_activity $quba, int $slot, string $feedback, float $score, float $maxscore): void {
        // Verify no manual grade has been applied.
        $fraction = $quba->get_question_fraction($slot);
        if ($fraction !== null && $fraction >= 0) {
            // Check if this was already graded by our plugin (check log).
            global $DB;
            $log = $DB->get_record('local_llmgrader_log', [
                'slot' => $slot,
                'status' => 'completed',
            ]);
            if ($log) {
                // Already graded by us, safe to update.
            } else {
                // May have been manually graded - skip to preserve teacher grades.
                return;
            }
        }

        $quba->manual_grade($slot, $feedback, $score / $maxscore, FORMAT_HTML);
        \question_engine::save_questions_usage_by_activity($quba);
    }
}
