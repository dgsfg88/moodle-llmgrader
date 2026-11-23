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
 * Unit tests for response parser.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_llmgrader;

use local_llmgrader\local\response_parser;

/**
 * Unit tests for response parser.
 *
 * @group local_llmgrader
 */
class response_parser_test extends \advanced_testcase {

    /**
     * Test valid JSON parsing.
     */
    public function test_parse_valid_json(): void {
        $parser = new response_parser();
        $raw = '{"score": 8, "max_score": 10, "comment": "Buona risposta."}';

        $result = $parser->parse_and_validate($raw, 10.0);

        $this->assertEquals(8.0, $result['score']);
        $this->assertEquals(10.0, $result['maxscore']);
        $this->assertEquals('Buona risposta.', $result['comment']);
    }

    /**
     * Test JSON extraction from markdown code blocks.
     */
    public function test_parse_json_from_markdown_block(): void {
        $parser = new response_parser();
        $raw = "```json\n{\"score\": 7, \"max_score\": 10, \"comment\": \"OK\"}\n```";

        $result = $parser->parse_and_validate($raw, 10.0);

        $this->assertEquals(7.0, $result['score']);
    }

    /**
     * Test score clamping to maxmark.
     */
    public function test_score_clamped_to_maxmark(): void {
        $parser = new response_parser();
        $raw = '{"score": 15, "max_score": 10, "comment": "Test"}';

        $result = $parser->parse_and_validate($raw, 10.0);

        // Score should be clamped to maxmark (10).
        $this->assertEquals(10.0, $result['score']);
    }

    /**
     * Test negative score rejection.
     */
    public function test_negative_score_rejected(): void {
        $this->expectException(\moodle_exception::class);

        $parser = new response_parser();
        $raw = '{"score": -1, "max_score": 10, "comment": "Test"}';

        $parser->parse_and_validate($raw, 10.0);
    }

    /**
     * Test missing score field rejection.
     */
    public function test_missing_score_rejected(): void {
        $this->expectException(\moodle_exception::class);

        $parser = new response_parser();
        $raw = '{"max_score": 10, "comment": "Test"}';

        $parser->parse_and_validate($raw, 10.0);
    }

    /**
     * Test missing comment field rejection.
     */
    public function test_missing_comment_rejected(): void {
        $this->expectException(\moodle_exception::class);

        $parser = new response_parser();
        $raw = '{"score": 5, "max_score": 10}';

        $parser->parse_and_validate($raw, 10.0);
    }

    /**
     * Test invalid JSON rejection.
     */
    public function test_invalid_json_rejected(): void {
        $this->expectException(\moodle_exception::class);

        $parser = new response_parser();
        $raw = 'not valid json at all';

        $parser->parse_and_validate($raw, 10.0);
    }

    /**
     * Test decimal score handling.
     */
    public function test_decimal_score(): void {
        $parser = new response_parser();
        $raw = '{"score": 7.5, "max_score": 10, "comment": "Decimal test"}';

        $result = $parser->parse_and_validate($raw, 10.0);

        $this->assertEquals(7.5, $result['score']);
    }

    /**
     * Test zero score acceptance.
     */
    public function test_zero_score(): void {
        $parser = new response_parser();
        $raw = '{"score": 0, "max_score": 10, "comment": "Zero response"}';

        $result = $parser->parse_and_validate($raw, 10.0);

        $this->assertEquals(0.0, $result['score']);
    }

    /**
     * Test NaN score rejection.
     */
    public function test_nan_score_rejected(): void {
        $this->expectException(\moodle_exception::class);

        $parser = new response_parser();
        $raw = '{"score": "NaN", "max_score": 10, "comment": "Test"}';

        $parser->parse_and_validate($raw, 10.0);
    }

    /**
     * Test weighted per-criterion grading.
     */
    public function test_weighted_criteria(): void {
        $parser = new response_parser();
        $raw = json_encode([
            'criteria' => [
                ['criterion' => 'Completezza', 'score' => 4, 'max_score' => 5, 'weight' => 30, 'comment' => 'Buona, è chiara.'],
                ['criterion' => 'Accuratezza', 'score' => 3, 'max_score' => 5, 'weight' => 25, 'comment' => 'Sufficiente.'],
                ['criterion' => 'Chiarezza', 'score' => 4, 'max_score' => 5, 'weight' => 10, 'comment' => 'Chiara.'],
            ],
            'overall_comment' => 'Prova complessivamente buona.',
        ], JSON_UNESCAPED_UNICODE);

        $result = $parser->parse_and_validate($raw, 10.0, 'it');

        // (4/5*30 + 3/5*25 + 4/5*10) / 65 * 10 = 7.2307...
        $this->assertEqualsWithDelta(7.23, $result['score'], 0.001);
        $this->assertCount(3, $result['criteria']);
        $this->assertStringContainsString('| Criterio |', $result['comment']);
        $this->assertStringContainsString('è', $result['comment']);
    }

    /**
     * Test recovery from a JSON response truncated by max_tokens.
     */
    public function test_truncated_json_recovered(): void {
        $parser = new response_parser();
        $full = json_encode([
            'criteria' => [
                ['criterion' => 'A', 'score' => 4, 'max_score' => 5, 'weight' => 50, 'comment' => 'Completo.'],
                ['criterion' => 'B', 'score' => 2, 'max_score' => 5, 'weight' => 50, 'comment' => 'Troncato'],
            ],
            'overall_comment' => 'Commento.',
        ], JSON_UNESCAPED_UNICODE);
        $cut = substr($full, 0, strpos($full, 'Troncato') + 3);

        $result = $parser->parse_and_validate($cut, 10.0, 'it');

        $this->assertEqualsWithDelta(8.0, $result['score'], 0.001);
        $this->assertStringContainsString('troncata', $result['comment']);
    }

    /**
     * Test a criterion without max_score is rejected.
     */
    public function test_criterion_without_max_score_rejected(): void {
        $this->expectException(\moodle_exception::class);

        $parser = new response_parser();
        $raw = json_encode([
            'criteria' => [
                ['criterion' => 'X', 'score' => 3, 'weight' => 1, 'comment' => 'Test'],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $parser->parse_and_validate($raw, 10.0, 'it');
    }
}
