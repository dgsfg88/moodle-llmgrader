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
        // Moodle content is expected to be UTF-8. Recover gracefully if a
        // legacy value is not, instead of producing invalid JSON later.
        if ($html !== '' && !mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
        }

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

        $markdown = $this->normalise_markdown($markdown);

        // Convert Markdown to HTML using Moodle's bundled Michelf library.
        // Raw HTML coming from the model is disabled: it must be treated as
        // text, never passed through as markup.
        $parser = new \Michelf\MarkdownExtra();
        $parser->no_markup = true;
        $html = $parser->transform($markdown);

        // Sanitize the resulting HTML using Moodle's HTMLPurifier.
        // purify_html($text, $options) - argument order matters.
        $html = purify_html($html, [
            'allowid' => false,
        ]);

        return $html;
    }

    /**
     * Normalise Markdown so common LLM formatting is rendered correctly.
     *
     * Markdown requires a blank line between a paragraph and a following list.
     * Models often emit "**Title:**\n* item" and the list would otherwise be
     * rendered as literal asterisks. Fenced code blocks are left untouched.
     *
     * @param string $markdown Raw Markdown.
     * @return string Normalised Markdown.
     */
    protected function normalise_markdown(string $markdown): string {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $markdown);
        $result = [];

        $listpattern = '/^\s{0,3}(?:[-*+]|\d+[.)])\s+/';
        $fencepattern = '/^\s*(?:```|~~~)/';

        $previousblank = true;
        $previouslist = false;
        $infence = false;

        foreach ($lines as $line) {
            $isfence = preg_match($fencepattern, $line) === 1;
            if ($isfence) {
                $infence = !$infence;
                $result[] = $line;
                $previousblank = false;
                $previouslist = false;
                continue;
            }

            if ($infence) {
                $result[] = $line;
                $previousblank = trim($line) === '';
                $previouslist = false;
                continue;
            }

            $islist = preg_match($listpattern, $line) === 1;
            if ($islist && !$previousblank && !$previouslist) {
                $result[] = '';
            }

            $result[] = $line;
            $previousblank = trim($line) === '';
            $previouslist = $islist;
        }

        return implode("\n", $result);
    }
}
