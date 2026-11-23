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
 * Unit tests for the prompt builder.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_llmgrader;

use local_llmgrader\local\prompt_builder;

/**
 * Unit tests for the prompt builder.
 *
 * @group local_llmgrader
 */
class prompt_builder_test extends \advanced_testcase {

    /**
     * Build a representative grading context.
     *
     * @return array
     */
    protected function make_context(): array {
        return [
            'questiontext' => 'Testo della domanda',
            'questiontextformat' => FORMAT_PLAIN,
            'question' => ['text' => 'Domanda', 'textformat' => FORMAT_PLAIN],
            'graderinfo' => '<p>Griglia con criterio.</p>',
            'graderinfoformat' => FORMAT_HTML,
            'studentresponse' => 'Risposta dello studente è accentata.',
            'studentresponseformat' => FORMAT_PLAIN,
            'maxmark' => 10.0,
            'feedbacklanguage' => 'it',
            'systemprompt' => 'Valuta secondo la griglia.',
        ];
    }

    /**
     * Test that the messages contain system and user roles.
     */
    public function test_builds_two_messages(): void {
        $builder = new prompt_builder();
        $messages = $builder->build_messages($this->make_context());

        $this->assertCount(2, $messages);
        $this->assertEquals('system', $messages[0]['role']);
        $this->assertEquals('user', $messages[1]['role']);
    }

    /**
     * Test that the mandatory contract and security rules are present.
     */
    public function test_contract_and_security_present(): void {
        $builder = new prompt_builder();
        $messages = $builder->build_messages($this->make_context());

        $system = $messages[0]['content'];
        $this->assertStringContainsString('CONTRATTO DI OUTPUT OBBLIGATORIO', $system);
        $this->assertStringContainsString('REGOLE DI SICUREZZA', $system);
        $this->assertStringContainsString('criteria', $system);
        $this->assertStringContainsString('overall_comment', $system);
    }

    /**
     * Test that untrusted blocks are delimited with a nonce.
     */
    public function test_delimited_blocks_with_nonce(): void {
        $builder = new prompt_builder();
        $messages = $builder->build_messages($this->make_context());

        $user = $messages[1]['content'];
        $this->assertMatchesRegularExpression(
            '/<<<LLMGRADER:([a-f0-9]{16}):QUESTION/',
            $user
        );
        $this->assertStringContainsString('LLMGRADER:', $user);
        $this->assertStringContainsString(':END>>>', $user);

        // All three untrusted blocks are present.
        $this->assertStringContainsString(':QUESTION', $user);
        $this->assertStringContainsString(':RUBRIC', $user);
        $this->assertStringContainsString(':STUDENT_RESPONSE', $user);
    }

    /**
     * Test that the same nonce is used consistently.
     */
    public function test_consistent_nonce(): void {
        $builder = new prompt_builder();
        $messages = $builder->build_messages($this->make_context());

        $user = $messages[1]['content'];
        preg_match_all('/<<<LLMGRADER:([a-f0-9]{16}):/', $user, $matches);
        $nonces = array_unique($matches[1]);

        $this->assertCount(1, $nonces);
    }

    /**
     * Test that UTF-8 survives prompt building.
     */
    public function test_utf8_preserved(): void {
        $builder = new prompt_builder();
        $messages = $builder->build_messages($this->make_context());

        $user = $messages[1]['content'];
        $this->assertStringContainsString('è accentata', $user);
        $this->assertStringNotContainsString('Ã¨', $user);
    }

    /**
     * Test that the teacher prompt is preserved but overridden by the contract.
     */
    public function test_teacher_prompt_preserved(): void {
        $builder = new prompt_builder();
        $messages = $builder->build_messages($this->make_context());

        $system = $messages[0]['content'];
        $this->assertStringContainsString('Valuta secondo la griglia.', $system);
    }
}
