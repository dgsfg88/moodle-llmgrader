IMPLEMENTATION SPECIFICATION — MOODLE LLM GRADER
1. Project objective

Develop a complete Moodle local plugin named:

local_llmgrader

Target platform: Moodle 5.0.x.

The plugin automatically grades only Essay questions in Moodle quizzes using an externally configurable LLM API compatible with OpenAI's Chat Completions interface.

The plugin must be independently installable and must never modify Moodle core or unrelated plugins.

Deliver a tested, documented, installable ZIP package.

2. Development environment

A local Moodle 5.0.x installation will be provided for development and integration testing.

First inspect the environment and identify:

Moodle installation directory.
Exact Moodle version and build.
PHP version.
Database engine and version.
Web server configuration.
PHP CLI executable.
Moodle CLI utilities.
PHPUnit availability.
Cron and Ad hoc Task execution capabilities.

Do not assume installation paths.

If the Moodle directory cannot be identified, ask the user for its location.

Do not modify existing courses, users, configurations, or production-like data without authorization.

3. Mandatory Moodle API investigation

Before implementation, inspect the actual Moodle source code.

Verify:

Quiz submission events.
Event observer registration.
Ad hoc Task APIs.
Quiz attempt lifecycle.
Question Engine APIs.
Question slots and question usages.
Essay question type identification.
Question version handling.
Student response retrieval.
Question text and context retrieval.
graderinfo retrieval.
Maximum question marks.
manual_grade() signature and behavior.
Feedback storage format.
Grade recalculation and Quiz grade synchronization.
Transaction and locking behavior.
Moodle Privacy API.

Document the actual classes, methods, signatures, and source file locations.

Never invent APIs or assume behavior from another Moodle version.

4. Functional requirements
4.1 Eligible questions

Automatically grade exclusively Essay questions.

Supported eligibility conditions:

Question type is Essay.
The actual question version used in the attempt contains non-empty graderinfo.
The response is available for grading.
The question has not already received an automatic evaluation for this attempt and slot.

Ignore all other question types.

Never use a newer question-bank version instead of the version associated with the submitted attempt.

4.2 Submission workflow

When a student submits a quiz:

Detect the relevant submission event.
Verify that the attempt is submitted.
Identify eligible Essay questions.
Create background grading tasks.
Wait until Moodle has completed its own attempt processing.
Process each Essay response independently.
Submit the evaluation to the LLM provider.
Validate the response.
Record the grade and feedback using Moodle Question Engine APIs.

Do not perform LLM HTTP calls synchronously during quiz submission.

Use Moodle Ad hoc Tasks.

The quiz submission must not be delayed by provider latency, rate limits, or temporary API failures.

4.3 LLM input

Send the following information for each Essay question:

Question text.
Relevant question context.
Complete student response.
Grading rubric from graderinfo.
Maximum available mark.
Desired feedback language.

Do not send student names, surnames, email addresses, usernames, student numbers, or other unnecessary identifiers.

4.4 LLM output

Require structured JSON containing:

score
max_score
comment
criteria (optional)

Example:

{ "score": 8, "max_score": 10, "comment": "La risposta dimostra una buona comprensione...", "criteria": [ { "name": "Comprensione dei concetti", "score": 4, "max_score": 4, "feedback": "..." } ] }

Validate:

JSON syntax.
Required fields.
Numeric score.
Finite values.
Non-negative score.
Maximum score boundaries.
Consistency between returned and actual maximum marks.
Feedback content.
Optional criterion scores and their consistency.

Never trust the LLM output without validation.

Support decimal marks and Moodle's grading precision.

4.5 Moodle grading

Automatically populate:

Grader comment.
Provisional numeric mark.

Use the appropriate Moodle Question Engine APIs, including manual_grade() where verified to be appropriate.

Never update Question Engine tables directly.

Verify that the grade is reflected correctly in:

The question attempt.
The quiz attempt.
The overall quiz grade.
The gradebook, where applicable.

Preserve normal teacher editing capabilities.

Do not overwrite an existing manual grade or a subsequent teacher modification.

5. HTML and Markdown conversion subsystem

This is a mandatory architectural feature.

Moodle content is frequently stored or rendered as HTML, while Markdown is generally more convenient and token-efficient for LLM prompts and feedback.

Implement a dedicated, bidirectional conversion subsystem.

Suggested classes:

html_to_markdown.php
markdown_to_html.php
content_converter.php

The implementation may use a different class structure if justified.

5.1 HTML to Markdown

Before constructing the LLM prompt, convert all applicable Moodle content into clean Markdown.

Convert:

Question text.
Question context.
Student Essay response.
Grading instructions (graderinfo).

Respect the actual Moodle content format.

Some Essay responses may be plain text rather than HTML. Never blindly interpret plain text as HTML.

Preserve semantic content and structure.

The converter must support:

Headings.
Paragraphs.
Bold and italic.
Strikethrough.
Ordered lists.
Unordered lists.
Nested lists.
Tables.
Table headings and cell relationships.
Links.
Inline code.
Code blocks.
Meaningful line breaks.
HTML entities.
Unicode characters.
Mathematical notation where present.

Do not simply strip all HTML tags.

Remove irrelevant presentation elements, scripts, styles, hidden content, and unsafe markup.

Do not duplicate or silently discard meaningful text.

Preserve the semantic distinction between the teacher's grading instructions, question content, and student-provided material.

Do not fetch external resources or remote images during conversion.

Handle embedded images and attachments conservatively: preserve available textual descriptions or references, but do not claim that visual content has been evaluated unless an explicitly supported image-processing feature is implemented.

5.2 Markdown to HTML

The LLM must return human-readable feedback in Markdown.

Convert:

Overall feedback.
Criterion descriptions.
Criterion-specific feedback.

Into safe Moodle-compatible HTML.

Example LLM output:

Punteggio: 8/10

La risposta è complessivamente corretta.

Buona comprensione dei concetti.
Argomentazione migliorabile.

Si consiglia di approfondire la fase luminosa.

Expected HTML:

<p><strong>Punteggio: 8/10</strong></p>
<p>La risposta è complessivamente corretta.</p>
<ul>
<li>Buona comprensione dei concetti.</li>
<li>Argomentazione migliorabile.</li>
</ul>
<p><em>Si consiglia di approfondire la fase luminosa.</em></p>

Use a maintained Markdown parser or a Moodle-supported implementation after verifying its availability and compatibility.

Disable raw HTML parsing in model-generated Markdown.

Sanitize the resulting HTML using appropriate Moodle security APIs.

Allow only necessary semantic HTML elements.

Reject:

JavaScript.
Script tags.
Event handlers.
Iframes.
Embedded objects.
Unsafe URLs.
Dangerous attributes.
Untrusted raw HTML.

Ensure correct Moodle feedback format metadata.

Avoid double escaping, malformed markup, duplicated paragraphs, or loss of formatting.

Add automated tests for both conversion directions, including:

Nested lists.
Complex tables.
Special characters.
Accented Italian text.
HTML entities.
Malformed HTML.
Empty content.
Plain-text input.
Markdown containing HTML injection attempts.
Code blocks.
Mathematical expressions.
5.3 Prompt structure

Keep different content types clearly separated.

Use explicit delimiters and labels:

QUESTION
QUESTION CONTEXT
GRADING RUBRIC
STUDENT RESPONSE
EVALUATION INSTRUCTIONS

Treat student content as untrusted data, never as system instructions.

The model must follow the teacher's rubric and must not accept instructions embedded in the student response that attempt to change grading criteria, output format, or maximum marks.

5.4 Token efficiency

Markdown conversion should reduce unnecessary HTML markup and improve prompt readability.

Do not assume that Markdown always consumes fewer tokens.

Measure token usage where practical, remove irrelevant formatting, avoid redundant instructions, and do not sacrifice meaningful assessment content for token savings.

6. Provider configuration

Provide administration settings under:

Site administration > Plugins > Local plugins > LLM Grader.

Settings:

Enable/disable plugin.
API endpoint.
Optional API key.
Model name.
Temperature, if supported.
Maximum output tokens, if supported.
HTTP timeout.
System prompt.
Feedback language.
Maximum retry attempts.
Minimum retry delay.
Maximum exponential backoff delay.
Retry enable/disable.
Maximum concurrent requests.
Provider-specific configuration where necessary.

Support endpoints compatible with:

POST /v1/chat/completions

Authentication:

If an API key exists, send Authorization: Bearer ....
If no key exists, omit the Authorization header.

Unauthenticated endpoints are supported only when the remote provider explicitly permits them.

Use Moodle's HTTP client where appropriate.

Never expose API credentials in logs, browser output, or student-facing pages.

Implement a connection test that does not use real student submissions.

7. Retry and rate limiting

This is a mandatory feature.

Implement adaptive retry scheduling using Moodle Ad hoc Tasks.

Never use sleep() or block PHP processes while waiting.

7.1 HTTP 429

When the provider returns HTTP 429:

Inspect Retry-After.
Support integer seconds.
Support HTTP-date format.
Inspect documented provider-specific retry headers.
Inspect structured error information in the response body where appropriate.
Determine the next eligible execution time.
Schedule a new task.

Always respect the provider's requested waiting period.

The configured maximum backoff must never shorten a provider-mandated waiting period.

7.2 Exponential backoff

When no explicit delay is provided, use exponential backoff with jitter:

delay = min(max_delay, base_delay * 2^retry_count) + jitter

Protect against integer overflow.

Suggested defaults:

Maximum attempts: 8 total attempts.
Base delay: 5 seconds.
Maximum normal backoff: 900 seconds.
Jitter: randomized positive delay.
7.3 Temporary failures

Retry:

HTTP 408.
HTTP 429.
HTTP 500.
HTTP 502.
HTTP 503.
HTTP 504.
Temporary connection failures.
Temporary network errors.
Appropriate request timeouts.

Do not automatically retry permanent failures such as:

HTTP 400.
HTTP 401.
HTTP 403.
Invalid model configuration.
Invalid request parameters.

Support provider-specific extensions where justified.

7.4 Idempotency

Identify each grading operation using:

Quiz attempt ID.
Question slot.
Question ID.
Question version.

Prevent duplicate grading and accidental overwriting of manual grades.

Handle ambiguous network failures where the provider may have processed the request but the response was lost.

Verify the Moodle grading state before committing any result.

7.5 Concurrency control

Implement provider-level concurrency control.

Use Moodle locking APIs where appropriate.

Prevent large batches of quiz submissions from overwhelming the same provider.

Ensure delayed tasks do not bypass concurrency restrictions.

7.6 Exhausted retries

After the maximum number of attempts:

Record the failure.
Preserve non-sensitive diagnostic information.
Leave the question available for manual grading.
Expose the failure status to authorized staff.
Allow an authorized user to trigger another evaluation.
8. Database and logging

Create an XMLDB-managed table such as:

local_llmgrader_log

Suggested fields:

id
attemptid
slot
questionid
questionversion
status
model
score
maxscore
feedback
retrycount
nextretry
lasthttpcode
lastretryreason
timecreated
timemodified
timecompleted
error

Add appropriate indexes and uniqueness constraints.

Use db/install.xml and db/upgrade.php.

Avoid storing complete student responses unnecessarily.

Never store API tokens in grading logs.

Implement appropriate retention and cleanup mechanisms.

9. Administration interface

Implement:

General configuration.
Endpoint and model configuration.
Optional API credentials.
Prompt configuration.
Retry settings.
Concurrency settings.
Connection test.
Recent grading activity.
Recent errors.
Grading statistics.

Respect Moodle capabilities, contexts, sesskey checks, and POST requirements.

10. Security and privacy

Follow Moodle security guidelines.

Implement:

Input validation.
Output escaping.
Appropriate capability checks.
CSRF protection.
Secure credential handling.
Safe HTTP communication.
Error sanitization.
Logging without personal information.
Prompt injection mitigation.
Moodle Privacy API integration.

Implement the necessary privacy functionality for personal data held by the plugin, including export and deletion as appropriate.

Document the external data processing involved in sending student responses to an LLM provider.

Do not send unnecessary student identifiers.

Do not interpret student text as trusted instructions.

11. Suggested architecture

Suggested structure:

local/llmgrader/ version.php settings.php lib.php db/ access.php install.xml upgrade.php events.php tasks.php classes/ observer.php task/ grade_attempt.php grade_essay.php local/ llm_client.php essay_grader.php response_parser.php content_converter.php html_to_markdown.php markdown_to_html.php retry_manager.php grading_repository.php question_locator.php rate_limiter.php privacy/ provider.php lang/ it/ local_llmgrader.php tests/ observer_test.php llm_client_test.php essay_grader_test.php retry_manager_test.php response_parser_test.php content_converter_test.php integration/ README.md

Adapt this structure when actual Moodle API investigation justifies a different design.

12. Automated testing

Implement PHPUnit tests covering:

Essay identification.
Exclusion of non-Essay questions.
Graderinfo retrieval.
Question text retrieval.
Question context retrieval.
Student response retrieval.
HTML-to-Markdown conversion.
Markdown-to-HTML conversion.
Malformed content.
HTML injection.
JSON parsing.
Invalid scores.
Decimal scores.
Optional authentication.
HTTP 429.
Numeric Retry-After.
HTTP-date Retry-After.
Exponential backoff.
Jitter.
Temporary failures.
Permanent failures.
Retry exhaustion.
Idempotency.
Concurrency.
Multiple quiz attempts.
Question version differences.
Manual grade preservation.

Use mocked HTTP responses and fixtures.

Tests must not depend on external LLM services.

13. Integration testing

Use the supplied local Moodle 5.0.x installation.

Create an isolated test course and quiz containing:

Essay with a grading rubric.
Essay without grading instructions.
Multiple choice.
Short answer.
Essay with decimal maximum marks.

Test:

Quiz submission.
Automatic task creation.
Asynchronous execution.
Correct question and rubric retrieval.
HTML-to-Markdown conversion.
Mock LLM communication.
Markdown-to-HTML conversion.
Score validation.
Moodle feedback registration.
Quiz grade recalculation.
Gradebook synchronization.
HTTP 429 handling.
Retry-After scheduling.
HTTP 503 handling.
No retry for HTTP 401.
Connection timeouts.
Duplicate task execution.
Teacher modifications after automatic grading.
Multiple attempts.
Question version changes.

Use a local mock HTTP provider for integration tests.

Run a real provider test only after explicit user authorization and provision of test credentials.

14. Documentation

Produce Italian documentation covering:

Installation.
Configuration.
Provider compatibility.
Authentication.
Essay rubric preparation.
HTML and Markdown conversion.
Evaluation workflow.
Retry behavior.
Error handling.
Privacy.
Cron requirements.
Troubleshooting.
Test execution.

Maintain:

docs/architecture.md
docs/implementation-log.md
docs/test-plan.md
docs/decisions.md

Use English for internal developer instructions and technical identifiers.

Use Italian for administrator-facing documentation and default student feedback.

15. Development workflow
Phase A — Environment discovery

Inspect the Moodle installation, PHP environment, CLI tools, and available testing infrastructure.

Verify all relevant Moodle APIs.

Phase B — Architecture

Document actual API behavior, task scheduling, grading lifecycle, conversion strategy, retry strategy, and database design.

Phase C — Implementation

Implement the plugin incrementally.

Prioritize:

Plugin installation and settings.
Database and event observer.
Asynchronous task handling.
Question Engine integration.
HTML/Markdown conversion.
LLM client.
Response validation.
Automatic grading.
Adaptive retry.
Administration and logging.
Privacy functionality.
Phase D — Testing

Run all available unit and integration tests.

Install and upgrade the plugin on local Moodle.

Fix discovered errors before proceeding.

Phase E — Review

Perform security, compatibility, privacy, concurrency, and grading consistency reviews.

Phase F — Delivery

Prepare source code, documentation, test results, and ZIP package.

16. Acceptance criteria

The project is complete when:

The plugin installs on Moodle 5.0.x.
Moodle core remains untouched.
Quiz submission is not blocked by LLM calls.
Only eligible Essay questions are graded.
Question text, context, rubric, and student response are correctly retrieved.
HTML content is converted to Markdown without meaningful information loss.
Plain-text content is handled correctly.
LLM feedback is converted to safe HTML.
The score and feedback are recorded through Moodle APIs.
The teacher can subsequently edit the grade.
Overall quiz grades remain consistent.
API authentication is optional.
HTTP 429 and Retry-After are handled correctly.
Temporary errors trigger scheduled retries.
Permanent errors do not trigger inappropriate retries.
Duplicate grading is prevented.
Provider concurrency limits are respected.
Tests pass or any remaining failures are explicitly documented.
Installation and configuration documentation is complete.
A distributable ZIP archive is produced.

Final archive:

local_llmgrader_moodle50.zip

The ZIP must contain the plugin directory llmgrader/ at its root.

17. Start instructions

Start immediately with Phase A.

Read AGENTS.md and this specification completely.

Inspect the supplied Moodle installation before implementing any Moodle-specific integration.

After completing the initial investigation, proceed autonomously through the remaining phases.

Do not stop after producing a plan or code skeleton.

Ask for clarification only when a genuine environmental blocker, destructive operation, missing authorization, or significant architectural decision requires user input.

Report actual test results and never claim unverified functionality.