# Implementation log

Chronological record of the work on `local_llmgrader`, including API discoveries,
defects found and their verification. Internal documentation, in English.

## 2026-11-21 — Multi-level configuration and first end-to-end run

- Verified the installed environment: Moodle 5.0.12, PHP 8.2.4, MariaDB 10.4.28.
- Implemented global, course and quiz configuration with inheritance
  (quiz → course → global, null values inherit).
- Confirmed that the quiz module context must be addressed with `cmid`; in the
  test installation `cmid=2` maps to quiz instance 1 and course 2.
- Confirmed that submitted quiz attempts are stored with state `finished`
  (`quiz_attempt::FINISHED`), not `submitted`.
- Confirmed that `quiz_attempts.uniqueid` is the question usage id.
- Confirmed that `question_usage_by_activity::manual_grade()` expects an
  absolute mark, not a fraction.
- Confirmed that `\core\http\Client` does not exist in Moodle 5.0; the supported
  class is `\core\http_client`.
- Added `allow_local_endpoints` because Moodle's SSRF protection blocks
  `127.0.0.1` by default.
- Mock end-to-end run succeeded: log `completed`, `0.75/1`, HTTP 200,
  `quiz_attempts.sumgrades=0.75`, `quiz_grades=7.5`, `grade_grades=7.5`,
  question state `mangrpartial`.

## 2026-11-23 — UTF-8, prompt isolation, per-criterion grading

### UTF-8 defect

- Symptom: Italian accents arrived at the model as `Ã¨`.
- Database values were valid UTF-8 (`è` = `C3 A8`), so the corruption happened
  during conversion.
- Root cause: `DOMDocument::loadHTML()` assumes ISO-8859-1 when no encoding is
  declared. A controlled test with four variants proved that the default
  variant double-encodes (`C3 83 C2 A8`), while a document with an XML encoding
  declaration or a `<meta charset>` produces correct bytes.
- Fix: prepend `<?xml encoding="UTF-8">` before `loadHTML()`, and recover
  invalid legacy byte sequences from Windows-1252 instead of dropping text.
- Verification: `è`, `€`, `“ ”` survive HTML → Markdown; the HTTP payload has
  no `Ã¨`.

### Prompt isolation

- The previous prompt concatenated sections with plain labels, making it easy
  for a student response to imitate a boundary.
- Rewrote `prompt_builder`:
  - every untrusted block is wrapped in
    `<<<LLMGRADER:<nonce>:NAME ... LLMGRADER:<nonce>:END>>>`;
  - the nonce is random per request and stripped from the content;
  - the system message always appends a mandatory JSON contract and explicit
    security rules, overriding conflicting teacher formatting instructions.
- Verified in the captured HTTP request: question, rubric and student response
  blocks, contract and security sections all present.

### Per-criterion grading

- The rubric in the test question has five criteria with weights
  30/25/25/10/10.
- The model now returns `criteria[]` with `criterion`, `score`, `max_score`,
  `weight` and `comment`, plus `overall_comment`.
- The plugin computes the weighted average:
  `sum(weight * score / max_score) / sum(weight) * max mark`.
- Feedback is generated as a Markdown table plus per-criterion comments and the
  overall comment, then converted to safe HTML.
- Legacy flat `{score, comment}` responses remain supported.

### Tolerant JSON parsing

- First real-LLM attempt (`max_tokens=2048`) failed with a truncated JSON
  object. Added, in order: trailing-comma removal, control-character escaping,
  and structural repair that keeps complete criteria and closes the arrays.
  Repaired responses carry a visible warning in the feedback.
- Second real-LLM attempt with `max_tokens=4096` completed in 107 s with five
  criteria. Weighted result 0.31/1 matched a manual recomputation
  (31/100).
- Grade propagation verified: `quiz_attempts.sumgrades=0.31`,
  `quiz_grades.grade=3.1`, `grade_grades.finalgrade=3.1`, question state
  `mangrpartial`.

### Markdown rendering

- The model emitted lists as `**Title:**\n* item`. Without a blank line,
  MarkdownExtra rendered the asterisks literally.
- Added a Markdown normaliser that inserts a blank line before a list when the
  previous line is plain text, while leaving fenced code blocks untouched.

### Version

- Bumped to 1.3.0 (`2026112300`); upgrade savepoint added.
- Defaults raised: `max_tokens=4096`, `http_timeout=300` seconds.
