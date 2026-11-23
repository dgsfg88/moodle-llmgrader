# Architectural decisions

This file records the decisions that shaped `local_llmgrader`, with the evidence
that led to each one. It is maintained in English because it is internal
developer documentation.

## ADR-001: Grade one criterion at a time, compute the final mark in PHP

**Status:** accepted (v1.3.0)

**Context:** Rubrics provided in `graderinfo` usually contain several criteria
with different weights. Asking the model for a single score produced unstable
results and made it impossible to explain the mark.

**Decision:** The model returns one object per criterion with `score`,
`max_score`, `weight` and `comment`. The plugin computes:

```
sum(weight * score / max_score) / sum(weight) * question maximum mark
```

**Consequences:** The model never controls the global mark. Missing or invalid
weights fall back to 1, and the computed value is clamped to the Moodle maximum
mark. A legacy flat `{score, comment}` response is still accepted.

## ADR-002: Wrap untrusted content in nonce-delimited blocks

**Status:** accepted (v1.3.0)

**Context:** Question text, rubric and student response were concatenated with
plain labels, so a student response could imitate a section boundary and inject
instructions.

**Decision:** Each untrusted block is wrapped as:

```
<<<LLMGRADER:<per-request nonce>:BLOCK
content
LLMGRADER:<nonce>:END>>>
```

The nonce is removed from the content before wrapping, so the block cannot be
closed early. The system message states that blocks are data, never
instructions.

**Consequences:** A new nonce is generated for every request. The prompt grows
by a few dozen characters, which is negligible.

## ADR-003: Always append a mandatory output contract

**Status:** accepted (v1.3.0)

**Context:** Administrators can customise the system prompt. A prompt asking
for a Markdown table (as in the test installation) conflicts with the JSON
contract and breaks parsing.

**Decision:** The system message is the teacher prompt followed by
`=== CONTRATTO DI OUTPUT OBBLIGATORIO ===` and `=== REGOLE DI SICUREZZA ===`.
The contract explicitly states that it overrides any conflicting format in the
teacher prompt.

**Consequences:** Custom prompts remain useful for tone and domain guidance but
cannot change the machine-readable format.

## ADR-004: Declare UTF-8 explicitly when parsing HTML

**Status:** accepted (v1.3.0)

**Context:** `DOMDocument::loadHTML()` assumes ISO-8859-1 when the document has
no encoding declaration. Accented characters were double-encoded
(`è` became `Ã¨`) before reaching the LLM.

**Decision:** Prepend `<?xml encoding="UTF-8">` before `loadHTML()`, and recover
invalid legacy byte sequences from Windows-1252 in the converter and parser.

**Consequences:** Italian accents, the euro sign and typographic quotes survive
HTML → Markdown. Verified with a byte-level test.

## ADR-005: Tolerate common local-model JSON defects

**Status:** accepted (v1.3.0)

**Context:** Local models sometimes emit trailing commas, literal newlines
inside strings, or stop in the middle of an object when `max_tokens` is
exhausted.

**Decision:** Before failing, the parser tries, in order: trailing-comma
removal, control-character escaping, and structural repair that keeps complete
criteria and closes the arrays. A repaired (truncated) response adds a visible
warning to the feedback.

**Consequences:** A truncated response no longer fails the whole grading, but
the teacher can see that the evaluation may be incomplete.

## ADR-006: Use Moodle's HTTP client and opt-in SSRF exception

**Status:** accepted (v1.2.0)

**Context:** Moodle blocks requests to localhost and private ranges to prevent
SSRF. Local LLM servers are a legitimate use case.

**Decision:** Use `\core\http_client` and check
`\core\files\curl_security_helper::url_is_blocked()`. Only when the
administrator enables `allow_local_endpoints` is the client created with
`ignoresecurity => true`.

**Consequences:** The default installation remains protected. The setting is
documented as a deliberate, administrator-only opt-in.

## ADR-007: Use Moodle-bundled Markdown and HTMLPurifier

**Status:** accepted (v1.1.0)

**Context:** LLM feedback is Markdown. Storing it as HTML without parsing or
sanitisation would allow script injection.

**Decision:** Use `\Michelf\MarkdownExtra` with `no_markup = true`, then
`purify_html($html, ['allowid' => false])`.

**Consequences:** Raw HTML from the model is disabled before sanitisation.
Tables, lists, code blocks and safe links are preserved.
