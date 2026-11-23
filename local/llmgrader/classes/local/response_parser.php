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
 * Response parser and validator for LLM grading output.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\local;

/**
 * Response parser class.
 *
 * Parses and validates the structured JSON response from the LLM.
 *
 * The preferred format contains one entry per rubric criterion, each with its
 * own score, maximum score, weight and comment. The final mark is computed by
 * the plugin as a weighted average, so the model never decides the global mark
 * directly.
 */
class response_parser {

    /** @var int Maximum length of the generated feedback, in characters. */
    const MAX_FEEDBACK_LENGTH = 12000;

    /**
     * Parse and validate the LLM response.
     *
     * @param string $rawresponse The raw text response from the LLM.
     * @param float $maxmark The maximum mark for the question.
     * @param string $language Feedback language code (it, en, ...).
     * @return array Parsed and validated result.
     * @throws \moodle_exception On parse or validation failure.
     */
    public function parse_and_validate(string $rawresponse, float $maxmark, string $language = 'it'): array {
        // Try to extract JSON from the response (handles markdown code blocks).
        $json = $this->extract_json($rawresponse);

        // Parse JSON, repairing the most common local-model defects.
        $repaired = false;
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $candidate = preg_replace('/,\s*([}\]])/', '$1', $json);
            $data = json_decode($candidate, true);
        }

        if (!is_array($data)) {
            // Some models emit literal newlines inside string values.
            $candidate = $this->escape_control_chars($candidate ?? $json);
            $data = json_decode($candidate, true);
        }

        if (!is_array($data)) {
            // The generation may have been cut off by max_tokens. Keep the
            // complete criteria and close the structure, flagging the result.
            $candidate = $this->truncate_to_last_complete_criterion($candidate ?? $json);
            if ($candidate !== null) {
                $data = json_decode($candidate, true);
                if (is_array($data)) {
                    $repaired = true;
                }
            }
        }

        if (!is_array($data)) {
            throw new \moodle_exception(
                'invalidjson',
                'local_llmgrader',
                '',
                $this->truncate($rawresponse, 500) . ' (' . json_last_error_msg() . ')'
            );
        }

        // Preferred format: per-criterion grading.
        if (isset($data['criteria']) && is_array($data['criteria']) && !empty($data['criteria'])) {
            return $this->parse_criteria_response($data, $maxmark, $language, $repaired);
        }

        // Backward-compatible flat format.
        $this->validate_required_fields($data);
        $score = $this->validate_score($data['score'], $maxmark);
        $comment = $this->validate_comment($data['comment'] ?? '');

        return [
            'score' => $score,
            'maxscore' => $maxmark,
            'comment' => $comment,
        ];
    }

    /**
     * Parse the per-criterion response and compute the weighted mark.
     *
     * @param array $data Decoded JSON.
     * @param float $maxmark Maximum mark for the question.
     * @param string $language Feedback language code.
     * @param bool $repaired Whether the JSON was recovered from a truncated response.
     * @return array
     * @throws \moodle_exception
     */
    protected function parse_criteria_response(array $data, float $maxmark, string $language, bool $repaired = false): array {
        $rawcriteria = $data['criteria'];

        // Accept a single criterion object instead of a list.
        if (isset($rawcriteria['criterion']) || isset($rawcriteria['name'])) {
            $rawcriteria = [$rawcriteria];
        }

        $criteria = [];
        $totalweight = 0.0;
        $weighted = 0.0;
        $index = 0;

        foreach ($rawcriteria as $rawcriterion) {
            $index++;
            if (!is_array($rawcriterion)) {
                throw new \moodle_exception('invalid_criterion', 'local_llmgrader', '', '#' . $index);
            }

            $name = $this->first_text($rawcriterion, ['criterion', 'name', 'label', 'criterio', 'criterium']);
            if ($name === '') {
                $name = 'Criterio ' . $index;
            }

            $score = $this->first_number($rawcriterion, ['score', 'grade', 'mark', 'voto', 'punteggio']);
            $maxscore = $this->first_number(
                $rawcriterion,
                ['max_score', 'maxscore', 'max', 'maximum', 'voto_massimo', 'punteggio_massimo']
            );
            $weight = $this->first_number($rawcriterion, ['weight', 'peso', 'ponderation', 'weight_percent']);

            if ($score === null) {
                throw new \moodle_exception('invalid_criterion', 'local_llmgrader', '', $name . ': score');
            }
            if ($maxscore === null || $maxscore <= 0) {
                throw new \moodle_exception('invalid_criterion', 'local_llmgrader', '', $name . ': max_score');
            }

            // A missing or non-positive weight falls back to 1 (equal weight).
            if ($weight === null || $weight <= 0) {
                $weight = 1.0;
            }

            // Clamp the criterion score to its own scale.
            $score = max(0.0, min((float)$score, (float)$maxscore));

            $comment = $this->first_text(
                $rawcriterion,
                ['comment', 'feedback', 'motivation', 'motivazione', 'giudizio', 'commento']
            );

            $criteria[] = [
                'criterion' => $name,
                'score' => $score,
                'max_score' => (float)$maxscore,
                'weight' => (float)$weight,
                'comment' => $comment,
            ];

            $totalweight += (float)$weight;
            $weighted += (float)$weight * ($score / (float)$maxscore);
        }

        if (empty($criteria)) {
            throw new \moodle_exception('missing_criteria', 'local_llmgrader');
        }

        // If every weight was zero, use equal weights.
        if ($totalweight <= 0) {
            $totalweight = (float)count($criteria);
            $weighted = 0.0;
            foreach ($criteria as $criterion) {
                $weighted += $criterion['score'] / $criterion['max_score'];
            }
        }

        $fraction = $weighted / $totalweight;
        $score = round($fraction * $maxmark, 2);
        $score = max(0.0, min($score, $maxmark));

        $overall = $this->first_text(
            $data,
            ['overall_comment', 'overall', 'comment', 'feedback', 'commento_complessivo']
        );

        $comment = $this->build_feedback($criteria, $overall, $score, $maxmark, $language, $repaired);

        return [
            'score' => $score,
            'maxscore' => $maxmark,
            'comment' => $comment,
            'criteria' => $criteria,
        ];
    }

    /**
     * Build the combined Markdown feedback from the per-criterion data.
     *
     * @param array $criteria Normalised criteria.
     * @param string $overall Overall comment from the model.
     * @param float $score Computed final score.
     * @param float $maxmark Maximum mark.
     * @param string $language Feedback language code.
     * @param bool $repaired Whether the JSON was recovered from a truncated response.
     * @return string Markdown feedback.
     */
    protected function build_feedback(array $criteria, string $overall, float $score, float $maxmark, string $language, bool $repaired = false): string {
        $labels = $this->get_labels($language);
        $parts = [];

        $totalweight = 0.0;
        foreach ($criteria as $criterion) {
            $totalweight += $criterion['weight'];
        }
        $weightsarepercent = abs($totalweight - 100.0) < 0.01;

        // Summary table. Markdown tables require contiguous rows, so the whole
        // table is emitted as a single part instead of one part per row.
        $parts[] = '### ' . $labels['criteria'];

        $tablerows = [];
        $tablerows[] = '| ' . $labels['criterion'] . ' | ' . $labels['score'] . ' | ' . $labels['max'] .
            ' | ' . $labels['weight'] . ' |';
        $tablerows[] = '|---|---:|---:|---:|';

        foreach ($criteria as $criterion) {
            $weightlabel = $this->format_number($criterion['weight']);
            if ($weightsarepercent) {
                $weightlabel .= '%';
            }
            $tablerows[] = '| ' . $this->escape_table_cell($criterion['criterion']) .
                ' | ' . $this->format_number($criterion['score']) .
                ' | ' . $this->format_number($criterion['max_score']) .
                ' | ' . $weightlabel . ' |';
        }

        $parts[] = implode("\n", $tablerows);

        $parts[] = '**' . $labels['total'] . ': ' . $this->format_number($score) . '/' .
            $this->format_number($maxmark) . '**';

        // Per-criterion comments.
        $hascomments = false;
        foreach ($criteria as $criterion) {
            if ($criterion['comment'] !== '') {
                $hascomments = true;
                break;
            }
        }

        if ($hascomments) {
            $parts[] = '### ' . $labels['feedback'];
            foreach ($criteria as $criterion) {
                if ($criterion['comment'] === '') {
                    continue;
                }
                $parts[] = '**' . $criterion['criterion'] . '** (' .
                    $this->format_number($criterion['score']) . '/' .
                    $this->format_number($criterion['max_score']) . ')';
                $parts[] = $criterion['comment'];
            }
        }

        if ($overall !== '') {
            $parts[] = '### ' . $labels['overall'];
            $parts[] = $overall;
        }

        if ($repaired) {
            $parts[] = '> **' . $labels['truncated'] . '**';
        }

        return $this->truncate(implode("\n\n", $parts), self::MAX_FEEDBACK_LENGTH);
    }

    /**
     * Localised labels for the generated feedback.
     *
     * @param string $language Language code.
     * @return array
     */
    protected function get_labels(string $language): array {
        if ($language === 'it') {
            return [
                'criteria' => 'Valutazione per criterio',
                'criterion' => 'Criterio',
                'score' => 'Punteggio',
                'max' => 'Massimo',
                'weight' => 'Peso',
                'total' => 'Punteggio complessivo',
                'feedback' => 'Feedback per criterio',
                'overall' => 'Commento complessivo',
                'truncated' => 'La risposta del modello è stata troncata: la valutazione potrebbe essere incompleta.',
            ];
        }

        return [
            'criteria' => 'Criterion evaluation',
            'criterion' => 'Criterion',
            'score' => 'Score',
            'max' => 'Maximum',
            'weight' => 'Weight',
            'total' => 'Overall score',
            'feedback' => 'Feedback per criterion',
            'overall' => 'Overall comment',
            'truncated' => 'The model response was truncated: the evaluation may be incomplete.',
        ];
    }

    /**
     * Format a number without unnecessary trailing zeros.
     *
     * @param float $value
     * @return string
     */
    protected function format_number(float $value): string {
        $formatted = number_format($value, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }

    /**
     * Escape a value for a Markdown table cell.
     *
     * @param string $value
     * @return string
     */
    protected function escape_table_cell(string $value): string {
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        return str_replace('|', '\|', trim($value));
    }

    /**
     * Extract JSON from the raw response (handles markdown code blocks).
     *
     * @param string $raw
     * @return string
     */
    protected function extract_json(string $raw): string {
        // Remove a UTF-8 BOM if present.
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $raw = trim($raw);

        // Check for a fenced Markdown code block.
        if (preg_match('/```(?:json)?\s*\n?([\s\S]*?)\n?```/', $raw, $matches)) {
            $raw = trim($matches[1]);
        }

        // If the model added text around the object, keep the outermost braces.
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            return substr($raw, $start, $end - $start + 1);
        }

        return $raw;
    }

    /**
     * Escape literal control characters that appear inside JSON strings.
     *
     * Local models sometimes emit real newlines inside a comment instead of
     * the escaped sequence, which makes json_decode() fail.
     *
     * @param string $json Raw JSON candidate.
     * @return string
     */
    protected function escape_control_chars(string $json): string {
        $out = '';
        $instring = false;
        $escape = false;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];

            if ($instring) {
                if ($escape) {
                    $out .= $char;
                    $escape = false;
                    continue;
                }
                if ($char === '\\') {
                    $out .= $char;
                    $escape = true;
                    continue;
                }
                if ($char === '"') {
                    $out .= $char;
                    $instring = false;
                    continue;
                }

                $ordinal = ord($char);
                if ($ordinal < 0x20) {
                    $map = ["\n" => '\\n', "\r" => '\\r', "\t" => '\\t'];
                    $out .= $map[$char] ?? sprintf('\\u%04x', $ordinal);
                    continue;
                }

                $out .= $char;
                continue;
            }

            if ($char === '"') {
                $instring = true;
            }
            $out .= $char;
        }

        return $out;
    }

    /**
     * Recover a JSON response cut off in the middle of a criterion.
     *
     * The function keeps every criterion object that was fully generated and
     * closes the "criteria" array and the root object. It returns null when no
     * complete criterion exists.
     *
     * @param string $json Raw JSON candidate.
     * @return string|null
     */
    protected function truncate_to_last_complete_criterion(string $json): ?string {
        $stack = [];
        $instring = false;
        $escape = false;
        $length = strlen($json);
        $lastgood = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];

            if ($instring) {
                if ($escape) {
                    $escape = false;
                } else if ($char === '\\') {
                    $escape = true;
                } else if ($char === '"') {
                    $instring = false;
                }
                continue;
            }

            if ($char === '"') {
                $instring = true;
                continue;
            }

            if ($char === '{' || $char === '[') {
                $stack[] = $char;
                continue;
            }

            if ($char === '}' || $char === ']') {
                $open = array_pop($stack);
                if ($open === null) {
                    return null;
                }

                // A criterion object closed: the remaining stack is the root
                // object followed by the "criteria" array.
                if ($char === '}' && count($stack) === 2 && $stack[0] === '{' && $stack[1] === '[') {
                    $lastgood = $i;
                }
            }
        }

        if ($lastgood === null) {
            return null;
        }

        return substr($json, 0, $lastgood + 1) . "\n]\n}";
    }

    /**
     * Validate required fields in the flat response format.
     *
     * @param array $data
     * @throws \moodle_exception
     */
    protected function validate_required_fields(array &$data): void {
        // Accept common aliases used by different models.
        if (!isset($data['score']) && isset($data['grade'])) {
            $data['score'] = $data['grade'];
        }
        if (!isset($data['comment']) && isset($data['feedback'])) {
            $data['comment'] = $data['feedback'];
        }

        if (!array_key_exists('score', $data)) {
            throw new \moodle_exception('missing_score', 'local_llmgrader');
        }
        if (!array_key_exists('comment', $data)) {
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
        // Normalise strings such as "7,5" or "7.5/10".
        if (is_string($score)) {
            $score = trim(str_replace(',', '.', $score));
            if (!is_numeric($score) && preg_match('/-?\d+(?:\.\d+)?/', $score, $matches)) {
                $score = $matches[0];
            }
        }

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
     * @param mixed $comment
     * @return string
     * @throws \moodle_exception
     */
    protected function validate_comment($comment): string {
        $comment = $this->normalise_text($comment);
        if ($comment === '') {
            throw new \moodle_exception('empty_comment', 'local_llmgrader');
        }

        return $this->truncate($comment, self::MAX_FEEDBACK_LENGTH);
    }

    /**
     * Return the first present key from a list of aliases.
     *
     * @param array $data
     * @param array $keys
     * @return mixed|null
     */
    protected function first_value(array $data, array $keys) {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }
        return null;
    }

    /**
     * Return the first present text value from a list of aliases.
     *
     * @param array $data
     * @param array $keys
     * @return string
     */
    protected function first_text(array $data, array $keys): string {
        return $this->normalise_text($this->first_value($data, $keys));
    }

    /**
     * Return the first present numeric value from a list of aliases.
     *
     * @param array $data
     * @param array $keys
     * @return float|null
     */
    protected function first_number(array $data, array $keys): ?float {
        $value = $this->first_value($data, $keys);
        if ($value === null) {
            return null;
        }
        return $this->parse_number($value);
    }

    /**
     * Parse a number from mixed input, accepting "7,5", "7.5/10" and "30%".
     *
     * @param mixed $value
     * @return float|null
     */
    protected function parse_number($value): ?float {
        if (is_int($value) || is_float($value)) {
            return is_finite((float)$value) ? (float)$value : null;
        }
        if (!is_string($value)) {
            return null;
        }

        $value = trim(str_replace(',', '.', $value));
        if (preg_match('/-?\d+(?:\.\d+)?/', $value, $matches)) {
            return (float)$matches[0];
        }

        return null;
    }

    /**
     * Normalise mixed input to a valid UTF-8 string.
     *
     * @param mixed $value
     * @return string
     */
    protected function normalise_text($value): string {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $value = (string)$value;
        if ($value !== '' && !mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return trim($value);
    }

    /**
     * Truncate a UTF-8 string safely.
     *
     * @param string $value
     * @param int $length
     * @return string
     */
    protected function truncate(string $value, int $length): string {
        if (\core_text::strlen($value) <= $length) {
            return $value;
        }
        return \core_text::substr($value, 0, $length);
    }
}
