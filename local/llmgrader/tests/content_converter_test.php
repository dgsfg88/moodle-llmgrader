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
 * Unit tests for content converter.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_llmgrader;

use local_llmgrader\local\content_converter;

/**
 * Unit tests for content converter.
 *
 * @group local_llmgrader
 */
class content_converter_test extends \advanced_testcase {

    /**
     * Test HTML to Markdown conversion.
     */
    public function test_html_to_markdown(): void {
        $converter = new content_converter();
        $html = '<p>Un paragrafo con <strong>grassetto</strong>.</p>';

        $result = $converter->html_to_markdown($html, FORMAT_HTML);

        $this->assertStringContainsString('**grassetto**', $result);
    }

    /**
     * Test plain text passthrough.
     */
    public function test_plain_text_passthrough(): void {
        $converter = new content_converter();
        $plaintext = 'Questo è un testo normale.';

        $result = $converter->html_to_markdown($plaintext, FORMAT_PLAIN);

        $this->assertEquals($plaintext, $result);
    }

    /**
     * Test Markdown to HTML conversion.
     */
    public function test_markdown_to_html(): void {
        $converter = new content_converter();
        $md = '**Grassetto** e *corsivo*';

        $result = $converter->markdown_to_html_safe($md);

        $this->assertStringContainsString('<strong>Grassetto</strong>', $result);
        $this->assertStringContainsString('<em>corsivo</em>', $result);
    }

    /**
     * Test Markdown list to HTML conversion.
     */
    public function test_markdown_list_to_html(): void {
        $converter = new content_converter();
        $md = "- Elemento uno\n- Elemento due";

        $result = $converter->markdown_to_html_safe($md);

        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>Elemento uno</li>', $result);
    }

    /**
     * Test empty content handling.
     */
    public function test_empty_content(): void {
        $converter = new content_converter();

        $markdown = $converter->markdown_to_html_safe('');
        $this->assertEquals('', $markdown);
    }

    /**
     * Test HTML sanitization - script tags removed.
     */
    public function test_script_removal_in_markdown_to_html(): void {
        $converter = new content_converter();
        // Try to inject HTML through markdown (should be sanitized).
        $md = 'Testo [link](javascript:alert(1))';

        $result = $converter->markdown_to_html_safe($md);

        // The Michelf parser might create a link, but purify_html should remove unsafe URLs.
        $this->assertStringNotContainsString('alert', $result);
    }

    /**
     * Test heading conversion.
     */
    public function test_markdown_heading_to_html(): void {
        $converter = new content_converter();
        $md = '# Titolo principale';

        $result = $converter->markdown_to_html_safe($md);

        $this->assertStringContainsString('<h1>', $result);
        $this->assertStringContainsString('Titolo principale', $result);
    }

    /**
     * Test code block conversion.
     */
    public function test_markdown_code_block_to_html(): void {
        $converter = new content_converter();
        $md = '```php\necho "test";\n```';

        $result = $converter->markdown_to_html_safe($md);

        $this->assertStringContainsString('<pre>', $result);
        $this->assertStringContainsString('code', strtolower($result));
    }
}
