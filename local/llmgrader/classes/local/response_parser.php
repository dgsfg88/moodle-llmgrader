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
 * Response parser and validator for LLM grading output.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\local;

// MOODLE_INTERNAL check removed - class is autoloaded

/**
 * Response parser class.
 *
 * Parses and validates the structured JSON response from the LLM.
 */
class response_parser {

    /** @var float Minimum acceptable score (fraction of max) */
    protected float $minscore;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->minscore = (float)(get_config('local_llmgrader', 'min_score') ?: 0);
    }

    /**
     * Parse and validate the LLM response.
     *
     * @param string $rawresponse The raw text response from the LLM.
     * @param float $maxmark The maximum mark for the question.
     * @return array Parsed and validated result.
     * @throws \moodle_exception On parse or validation failure.
     */
    public function parse_and_validate(string $rawresponse, float $maxmark): array {
        // Try to extract JSON from the response (handles markdown code blocks).
        $json = $this->extract_json($rawresponse);

        // Parse JSON.
        $data = @json_decode($json, true);
        if (!$data || !is_array($data)) {
            throw new \moodle_exception('invalidjson', 'local_llmgrader', '', substr($rawresponse, 0, 500));
        }

        // Validate required fields.
        $this->validate_required_fields($data);

        // Extract and validate score.
        $score = $this->validate_score($data['score'], $maxmark);

        // Validate comment/feedback.
        $comment = $this->validate_comment($data['comment'] ?? '');

        return [
            'score' => $score,
            'maxscore' => $maxmark,
            'comment' => $comment,
        ];
    }

    /**
     * Extract JSON from the raw response (handles markdown code blocks).
     *
     * @param string $raw
     * @return string
     */
    protected function extract_json(string $raw): string {
        // Check for markdown code block.
        if (preg_match('/```(?:json)?\s*\n?([\s\S]*?)\n?```/', $raw, $matches)) {
            return $matches[1];
        }
        return trim($raw);
    }

    /**
     * Validate required fields in the response.
     *
     * @param array $data
     * @throws \moodle_exception
     */
    protected function validate_required_fields(array $data): void {
        if (!isset($data['score'])) {
            throw new \moodle_exception('missing_score', 'local_llmgrader');
        }
        if (!isset($data['comment'])) {
            throw new \moodle_exception('missing_comment', 'local_llmgrader');
        }
    }

    /**
     * Validate the score value.
     *
     * @param mixed $score
     * @param float $maxmark
     * @return float
     * @throws \moodle_exception
     */
    protected function validate_score($score, float $maxmark): float {
        // Ensure it's a finite number.
        if (!is_numeric($score) || !is_finite(floatval($score))) {
            throw new \moodle_exception('invalid_score', 'local_llmgrader');
        }

        $score = round(floatval($score), 2);

        // Score must be non-negative.
        if ($score < 0) {
            throw new \moodle_exception('negative_score', 'local_llmgrader');
        }

        // Score must not exceed the question's max mark.
        if ($score > $maxmark) {
            $score = $maxmark;
        }

        return $score;
    }

    /**
     * Validate and sanitize the comment/feedback text.
     *
     * @param string $comment
     * @return string
     * @throws \moodle_exception
     */
    protected function validate_comment(string $comment): string {
        if (empty($comment)) {
            throw new \moodle_exception('empty_comment', 'local_llmgrader');
        }

        // Truncate excessively long comments.
        if (strlen($comment) > 5000) {
            $comment = substr($comment, 0, 5000);
        }

        return trim($comment);
    }
}
