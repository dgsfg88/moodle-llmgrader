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
 * HTML to Markdown converter for Moodle content.
 *
 * Uses DOMDocument parsing to convert Moodle-rendered HTML to clean Markdown.
 * Handles headings, lists, tables, code blocks, links, bold/italic, and more.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\local;

// MOODLE_INTERNAL check removed - class is autoloaded

/**
 * HTML to Markdown converter.
 */
class html_to_markdown {

    /** @var \DOMDocument */
    protected \DOMDocument $dom;

    /** @var string Converted Markdown */
    protected string $markdown = '';

    /** @var int Current indentation level */
    protected int $indent = 0;

    /**
     * Convert HTML to Markdown.
     *
     * @param string $html The HTML content.
     * @return string Markdown text.
     */
    public function convert(string $html): string {
        // Clean the HTML first.
        $html = $this->clean_html($html);

        // Parse with DOMDocument.
        // DOMDocument::loadHTML() assumes ISO-8859-1 when no encoding is
        // declared, which double-encodes every non-ASCII character. The XML
        // encoding declaration forces UTF-8 interpretation.
        $this->dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $this->dom->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $this->markdown = '';
        $this->indent = 0;

        // Process child nodes.
        foreach ($this->dom->documentElement->childNodes as $node) {
            $this->process_node($node);
        }

        // Clean up the output.
        $result = preg_replace('/\n{3,}/', "\n\n", $this->markdown);
        $result = trim($result);

        return $result;
    }

    /**
     * Clean HTML before conversion.
     *
     * @param string $html
     * @return string
     */
    protected function clean_html(string $html): string {
        // Remove scripts and styles.
        $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);

        // Remove comments.
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // Remove hidden elements.
        $html = preg_replace('/<([^>]+)(?:class|style)\s*=[^>]*display\s*:\s*none[^>]*>(.*?)<\/\1>/si', '', $html);

        // Do not collapse whitespace globally: it would destroy the content of
        // <pre> and <code> blocks. Whitespace is normalised per text node.
        return $html;
    }

    /**
     * Append a text node to the Markdown output.
     *
     * @param string $text
     */
    protected function append_text(string $text): void {
        if ($text === '') {
            return;
        }

        // Collapse runs of whitespace, but keep a single separating space.
        $text = preg_replace('/[ \t\r\n]+/u', ' ', $text);
        if ($text === null) {
            // Invalid UTF-8: recover instead of dropping the text.
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        $this->markdown .= $text;
    }

    /**
     * Process a DOM node and append Markdown.
     *
     * @param \DOMNode $node
     */
    protected function process_node(\DOMNode $node): void {
        switch ($node->nodeType) {
            case XML_TEXT_NODE:
                $this->append_text($node->textContent);
                break;

            case XML_ELEMENT_NODE:
                $this->process_element($node);
                break;
        }
    }

    /**
     * Process an element node.
     *
     * @param \DOMElement $element
     */
    protected function process_element(\DOMElement $element): void {
        $tag = strtolower($element->tagName);

        switch ($tag) {
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
                $this->process_heading($element, (int)substr($tag, 1));
                break;

            case 'p':
            case 'div':
            case 'blockquote':
                $this->process_block($element);
                break;

            case 'br':
                $this->append("  \n");
                break;

            case 'hr':
                $this->append("\n---\n\n");
                break;

            case 'strong':
            case 'b':
                $this->process_inline($element, '**');
                break;

            case 'em':
            case 'i':
                $this->process_inline($element, '*');
                break;

            case 'del':
            case 's':
            case 'strike':
                $this->process_inline($element, '~~');
                break;

            case 'code':
                if ($this->is_code_block_parent($element)) {
                    $this->process_code_block($element);
                } else {
                    $this->append('`' . $this->get_text_content($element) . '`');
                }
                break;

            case 'pre':
                $this->process_pre_block($element);
                break;

            case 'a':
                $this->process_link($element);
                break;

            case 'ul':
            case 'ol':
                $this->process_list($element, $tag === 'ol');
                break;

            case 'table':
                $this->process_table($element);
                break;

            case 'img':
                $alt = $element->getAttribute('alt');
                if ($alt) {
                    $this->append("[{$alt}]");
                }
                break;

            default:
                // For unknown elements, process children.
                foreach ($element->childNodes as $child) {
                    $this->process_node($child);
                }
                break;
        }
    }

    /**
     * Process heading elements.
     *
     * @param \DOMElement $element
     * @param int $level
     */
    protected function process_heading(\DOMElement $element, int $level): void {
        $prefix = str_repeat('#', $level) . ' ';
        $text = trim($this->get_text_content($element));
        $this->append("\n{$prefix}{$text}\n\n");
    }

    /**
     * Process block-level elements.
     *
     * @param \DOMElement $element
     */
    protected function process_block(\DOMElement $element): void {
        $tag = strtolower($element->tagName);
        $prefix = ($tag === 'blockquote') ? '> ' : '';

        if ($prefix) {
            $this->append("\n");
        }

        foreach ($element->childNodes as $child) {
            $this->process_node($child);
        }

        if (!$prefix) {
            $this->append("\n\n");
        }
    }

    /**
     * Process inline formatting elements.
     *
     * @param \DOMElement $element
     * @param string $wrapper
     */
    protected function process_inline(\DOMElement $element, string $wrapper): void {
        $text = trim($this->get_text_content($element));
        if ($text) {
            $this->append("{$wrapper}{$text}{$wrapper}");
        }
    }

    /**
     * Process a link element.
     *
     * @param \DOMElement $element
     */
    protected function process_link(\DOMElement $element): void {
        $href = $element->getAttribute('href');
        $text = trim($this->get_text_content($element));

        // Only allow safe URLs.
        if ($href && $this->is_safe_url($href)) {
            $this->append("[{$text}]({$href})");
        } else {
            $this->append($text);
        }
    }

    /**
     * Process list elements.
     *
     * @param \DOMElement $element
     * @param bool $ordered
     */
    protected function process_list(\DOMElement $element, bool $ordered): void {
        $this->append("\n");
        $index = 1;

        foreach ($element->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->tagName) === 'li') {
                $prefix = $ordered ? "{$index}. " : '- ';
                $text = trim($this->get_text_content($child));

                // Check for nested content.
                $hasnested = false;
                foreach ($child->childNodes as $inner) {
                    if ($inner->nodeType === XML_ELEMENT_NODE) {
                        $hasnested = true;
                        break;
                    }
                }

                if ($hasnested) {
                    // Process children recursively.
                    $first = true;
                    foreach ($child->childNodes as $inner) {
                        if ($first) {
                            $this->append($prefix);
                            $first = false;
                        }
                        $this->process_node($inner);
                    }
                } else {
                    $this->append("{$prefix}{$text}\n");
                }

                $index++;
            }
        }

        $this->append("\n");
    }

    /**
     * Process a table element.
     *
     * @param \DOMElement $element
     */
    protected function process_table(\DOMElement $element): void {
        $this->append("\n");
        $rows = [];

        // Extract rows, supporting both direct <tr> children and the usual
        // <thead>/<tbody>/<tfoot> wrappers.
        foreach ($element->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['thead', 'tbody', 'tfoot'], true)) {
                $this->collect_table_rows($child, $rows);
            } else if ($tag === 'tr') {
                $this->collect_table_row($child, $rows);
            }
        }

        // Build the Markdown table, inserting the separator row exactly once.
        $separatorwritten = false;
        $first = true;
        foreach ($rows as $row) {
            $cells = array_map([$this, 'escape_table_cell'], $row['cells']);
            $this->append('|' . implode('|', $cells) . "|\n");

            if (!$separatorwritten && ($row['isheader'] || $first)) {
                $separator = array_fill(0, count($row['cells']), '---');
                $this->append('|' . implode('|', $separator) . "|\n");
                $separatorwritten = true;
            }
            $first = false;
        }

        $this->append("\n");
    }

    /**
     * Collect table rows from a thead/tbody/tfoot container.
     *
     * @param \DOMElement $container
     * @param array $rows
     */
    protected function collect_table_rows(\DOMElement $container, array &$rows): void {
        foreach ($container->childNodes as $row) {
            if ($row->nodeType === XML_ELEMENT_NODE && strtolower($row->tagName) === 'tr') {
                $this->collect_table_row($row, $rows);
            }
        }
    }

    /**
     * Collect a single table row.
     *
     * @param \DOMElement $row
     * @param array $rows
     */
    protected function collect_table_row(\DOMElement $row, array &$rows): void {
        $cells = [];
        $isheader = false;

        foreach ($row->childNodes as $cell) {
            if ($cell->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $tag = strtolower($cell->tagName);
            if (!in_array($tag, ['td', 'th'], true)) {
                continue;
            }
            if ($tag === 'th') {
                $isheader = true;
            }
            $text = trim(preg_replace('/\s+/u', ' ', $this->get_text_content($cell)));
            $cells[] = $text;
        }

        if ($cells) {
            $rows[] = ['cells' => $cells, 'isheader' => $isheader];
        }
    }

    /**
     * Escape pipe characters inside a Markdown table cell.
     *
     * @param string $cell
     * @return string
     */
    protected function escape_table_cell(string $cell): string {
        return str_replace('|', '\\|', $cell);
    }

    /**
     * Process a code block (pre/code).
     *
     * @param \DOMElement $element
     */
    protected function process_pre_block(\DOMElement $element): void {
        $code = '';
        foreach ($element->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $code .= $child->textContent;
            } elseif ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->tagName) === 'code') {
                $code .= $this->get_text_content($child);
            }
        }

        // Try to detect language from class attribute.
        $class = $element->getAttribute('class');
        $lang = '';
        if (preg_match('/language-(\w+)/', $class, $matches)) {
            $lang = $matches[1];
        }

        $this->append("\n```{$lang}\n" . trim($code) . "\n```\n\n");
    }

    /**
     * Process a code element inside pre.
     *
     * @param \DOMElement $element
     */
    protected function process_code_block(\DOMElement $element): void {
        // This is handled by process_pre_block.
    }

    /**
     * Get plain text content from an element (recursively).
     *
     * @param \DOMElement $element
     * @return string
     */
    protected function get_text_content(\DOMElement $element): string {
        $text = '';
        foreach ($element->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['br', 'hr', 'p', 'div', 'li', 'tr'])) {
                    $text .= "\n";
                }
                $text .= $this->get_text_content($child);
            }
        }
        return $text;
    }

    /**
     * Append text to the Markdown output.
     *
     * @param string $text
     */
    protected function append(string $text): void {
        $this->markdown .= $text;
    }

    /**
     * Check if an element is inside a code block parent.
     *
     * @param \DOMElement $element
     * @return bool
     */
    protected function is_code_block_parent(\DOMElement $element): bool {
        $parent = $element->parentNode;
        while ($parent) {
            if ($parent->nodeType === XML_ELEMENT_NODE && strtolower($parent->tagName) === 'pre') {
                return true;
            }
            $parent = $parent->parentNode;
        }
        return false;
    }

    /**
     * Check if a URL is safe.
     *
     * @param string $url
     * @return bool
     */
    protected function is_safe_url(string $url): bool {
        // Allow relative URLs and safe schemes.
        if (strpos($url, '/') === 0 || strpos($url, '#') === 0) {
            return true;
        }

        $parsed = parse_url($url);
        if (!$parsed) {
            return false;
        }

        $scheme = strtolower($parsed['scheme'] ?? 'http');
        return in_array($scheme, ['http', 'https'], true);
    }
}
