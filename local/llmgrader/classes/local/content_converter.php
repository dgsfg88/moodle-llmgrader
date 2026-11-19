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
 * Bidirectional HTML/Markdown content converter.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\local;

// MOODLE_INTERNAL check removed - class is autoloaded

/**
 * Content converter class.
 *
 * Provides bidirectional conversion between Moodle HTML and LLM-friendly Markdown.
 */
class content_converter {

    /** @var html_to_markdown */
    protected html_to_markdown $htmldown;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->htmldown = new html_to_markdown();
    }

    /**
     * Convert Moodle HTML content to Markdown for LLM prompts.
     *
     * Respects the original format - plain text is not interpreted as HTML.
     *
     * @param string $html The HTML content to convert.
     * @param int $format The original format constant (FORMAT_HTML, FORMAT_PLAIN, etc.).
     * @return string Markdown text.
     */
    public function html_to_markdown(string $html, int $format = FORMAT_HTML): string {
        // If the content is plain text or markdown, return as-is.
        if ($format === FORMAT_PLAIN || $format === FORMAT_MARKDOWN) {
            return $html;
        }

        // If it looks like plain text (no HTML tags), return as-is.
        if (!preg_match('/<[^>]+>/', $html)) {
            return $html;
        }

        return $this->htmldown->convert($html);
    }

    /**
     * Convert Markdown feedback from LLM to safe Moodle-compatible HTML.
     *
     * Uses Michelf MarkdownExtra (bundled with Moodle) and sanitizes the output.
     *
     * @param string $markdown The Markdown text to convert.
     * @return string Sanitized HTML.
     */
    public function markdown_to_html_safe(string $markdown): string {
        if (empty($markdown)) {
            return '';
        }

        // Convert Markdown to HTML using Moodle's bundled Michelf library.
        $html = \Michelf\MarkdownExtra::defaultTransform($markdown);

        // Sanitize the resulting HTML using Moodle's HTMLPurifier.
        $html = purify_html([
            'allowid' => false,
        ], $html);

        return $html;
    }
}
