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
 * Unit tests for HTML to Markdown converter.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_llmgrader;

use local_llmgrader\local\html_to_markdown;

/**
 * Unit tests for HTML to Markdown converter.
 *
 * @group local_llmgrader
 */
class html_to_markdown_test extends \advanced_testcase {

    /**
     * Test heading conversion.
     */
    public function test_heading_conversion(): void {
        $converter = new html_to_markdown();
        $html = '<h1>Titolo</h1><h2>Sottotitolo</h2>';

        $result = $converter->convert($html);

        $this->assertStringContainsString('# Titolo', $result);
        $this->assertStringContainsString('## Sottotitolo', $result);
    }

    /**
     * Test bold and italic conversion.
     */
    public function test_bold_italic_conversion(): void {
        $converter = new html_to_markdown();
        $html = '<p>Testo <strong>grassetto</strong> e <em>corsivo</em></p>';

        $result = $converter->convert($html);

        $this->assertStringContainsString('**grassetto**', $result);
        $this->assertStringContainsString('*corsivo*', $result);
    }

    /**
     * Test unordered list conversion.
     */
    public function test_unordered_list_conversion(): void {
        $converter = new html_to_markdown();
        $html = '<ul><li>Primo</li><li>Secondo</li></ul>';

        $result = $converter->convert($html);

        $this->assertStringContainsString('- Primo', $result);
        $this->assertStringContainsString('- Secondo', $result);
    }

    /**
     * Test ordered list conversion.
     */
    public function test_ordered_list_conversion(): void {
        $converter = new html_to_markdown();
        $html = '<ol><li>Primo</li><li>Secondo</li></ol>';

        $result = $converter->convert($html);

        $this->assertStringContainsString('1. Primo', $result);
        $this->assertStringContainsString('2. Secondo', $result);
    }

    /**
     * Test link conversion with safe URL.
     */
    public function test_link_conversion(): void {
        $converter = new html_to_markdown();
        $html = '<p>Clicca <a href="https://example.com">qui</a>.</p>';

        $result = $converter->convert($html);

        $this->assertStringContainsString('[qui](https://example.com)', $result);
    }

    /**
     * Test inline code conversion.
     */
    public function test_inline_code_conversion(): void {
        $converter = new html_to_markdown();
        $html = '<p>Usa il comando <code>ls -la</code>.</p>';

        $result = $converter->convert($html);

        $this->assertStringContainsString('`ls -la`', $result);
    }

    /**
     * Test plain text passthrough.
     */
    public function test_plain_text_passthrough(): void {
        $converter = new html_to_markdown();
        $html = 'Questo è un testo semplice senza HTML.';

        $result = $converter->convert($html);

        $this->assertEquals('Questo è un testo semplice senza HTML.', $result);
    }

    /**
     * Test script removal.
     */
    public function test_script_removal(): void {
        $converter = new html_to_markdown();
        $html = '<p>Testo <script>alert("XSS")</script> sicuro</p>';

        $result = $converter->convert($html);

        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringNotContainsString('script', strtolower($result));
    }

    /**
     * Test code block conversion.
     */
    public function test_code_block_conversion(): void {
        $converter = new html_to_markdown();
        $html = '<pre><code class="language-php">echo "hello";</code></pre>';

        $result = $converter->convert($html);

        $this->assertStringContainsString('```php', $result);
        $this->assertStringContainsString('echo "hello";', $result);
        $this->assertStringContainsString('```', $result);
    }

    /**
     * Test table conversion.
     */
    public function test_table_conversion(): void {
        $converter = new html_to_markdown();
        $html = '<table><thead><tr><th>Colonna 1</th><th>Colonna 2</th></tr></thead><tbody><tr><td>A</td><td>B</td></tr></tbody></table>';

        $result = $converter->convert($html);

        $this->assertStringContainsString('| Colonna 1', $result);
        $this->assertStringContainsString('|---|---|', $result);
        $this->assertStringContainsString('| A | B |', $result);
    }

    /**
     * Test HTML entity handling.
     */
    public function test_html_entity_handling(): void {
        $converter = new html_to_markdown();
        $html = '<p>Prezzo: &euro;10 &amp; &quot;buono&quot;</p>';

        $result = $converter->convert($html);

        // Entities should be decoded.
        $this->assertStringContainsString('€', $result);
    }

    /**
     * Test Italian text handling.
     */
    public function test_italian_text_handling(): void {
        $converter = new html_to_markdown();
        $html = '<p>La risposta dimostra una buona comprensione dei concetti.</p>';

        $result = $converter->convert($html);

        $this->assertStringContainsString('La risposta dimostra', $result);
    }

    /**
     * Test empty content handling.
     */
    public function test_empty_content(): void {
        $converter = new html_to_markdown();

        $result = $converter->convert('');

        $this->assertEquals('', $result);
    }

    /**
     * Test HTML injection in links is prevented.
     */
    public function test_unsafe_url_blocked(): void {
        $converter = new html_to_markdown();
        $html = '<p><a href="javascript:alert(1)">clicca</a></p>';

        $result = $converter->convert($html);

        // The javascript: URL should not appear as a link.
        $this->assertStringNotContainsString('javascript:', $result);
    }
}
