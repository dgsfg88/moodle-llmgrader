ARCHITECTURE.md
Moodle LLM Grader — System Architecture
1. Overview

The project implements a Moodle local plugin named local_llmgrader.

Its purpose is to automatically evaluate Essay questions in Moodle quizzes using an externally configurable Large Language Model (LLM) API compatible with the OpenAI Chat Completions interface.

Target platform:

Moodle 5.0.x
PHP version supported by the installed Moodle release
Moodle-supported database engines
OpenAI-compatible HTTP Chat Completions APIs

The plugin must remain fully independent of Moodle core and other plugins.

All grading operations must use Moodle's supported APIs.

The architecture prioritizes:

Correct integration with Moodle's Question Engine.
Asynchronous execution.
Preservation of teacher control.
Reliable and idempotent grading.
Safe HTML/Markdown conversion.
Resilient LLM communication.
Data privacy and security.
Testability and maintainability.
2. Architectural principles
2.1 Moodle-native integration

The plugin must use Moodle's official extension mechanisms:

Event observers.
Ad hoc Tasks.
Question Engine APIs.
Quiz APIs.
Moodle database abstraction.
Moodle locking API.
Moodle HTTP client.
Moodle Privacy API.
Moodle capability and permission mechanisms.

Direct modification of Moodle core or Question Engine database tables is strictly prohibited.

2.2 Asynchronous processing

LLM requests must never execute synchronously during quiz submission.

Quiz submission triggers the creation of background grading tasks.

The actual grading process is executed by Moodle's Ad hoc Task system.

This ensures that:

Student submission is not delayed by LLM latency.
Provider outages do not block Moodle.
Rate limits can be handled without blocking PHP processes.
Retry scheduling remains independent of the original quiz submission.
2.3 Separation of responsibilities

The implementation must separate:

Moodle event integration.
Question and attempt retrieval.
Grading orchestration.
Content conversion.
LLM communication.
Response validation.
Retry management.
Persistence and logging.
Administration.

Each component should have a well-defined responsibility and should be independently testable.

3. High-level architecture

The main processing pipeline is:

text
Moodle Quiz
    |
    v
Quiz Submission Event
    |
    v
Event Observer
    |
    v
Attempt Eligibility Inspection
    |
    v
Question Locator
    |
    +---- Essay questions only
    +---- Valid graderinfo required
    +---- Actual attempt question version
    +---- Available student response
    |
    v
Create Ad hoc Grading Tasks
    |
    v
Moodle Cron / Ad hoc Task Runner
    |
    v
Grading Task
    |
    v
Question and Response Retrieval
    |
    v
HTML / Markdown Content Converter
    |
    v
Prompt Construction
    |
    v
LLM Provider Client
    |
    v
Structured Response Parser
    |
    v
Grade and Feedback Validation
    |
    v
Question Engine Grading API
    |
    v
Quiz Grade Recalculation
    |
    v
Grading Repository / Audit Log


Retry processing follows a separate scheduling path:

text
LLM Request
    |
    v
HTTP Response
    |
    +---- Success
    |       |
    |       v
    |   Validate and Grade
    |
    +---- Temporary Failure
    |       |
    |       v
    |   Retry Manager
    |       |
    |       v
    |   Calculate Next Execution Time
    |       |
    |       v
    |   Reschedule Ad hoc Task
    |
    +---- Permanent Failure
            |
            v
        Record Failure
            |
            v
        Manual Grading Required

4. Plugin structure

Proposed directory structure:

text
local/llmgrader/
|
+-- version.php
+-- settings.php
+-- lib.php
+-- README.md
|
+-- db/
|   +-- access.php
|   +-- install.xml
|   +-- upgrade.php
|   +-- events.php
|   +-- tasks.php
|
+-- classes/
|   |
|   +-- observer.php
|   |
|   +-- task/
|   |   +-- grade_attempt.php
|   |   +-- grade_essay.php
|   |
|   +-- local/
|   |   +-- question_locator.php
|   |   +-- essay_grader.php
|   |   +-- grading_repository.php
|   |   +-- llm_client.php
|   |   +-- response_parser.php
|   |   +-- content_converter.php
|   |   +-- html_to_markdown.php
|   |   +-- markdown_to_html.php
|   |   +-- retry_manager.php
|   |   +-- rate_limiter.php
|   |   +-- prompt_builder.php
|   |
|   +-- privacy/
|       +-- provider.php
|
+-- lang/
|   +-- it/
|       +-- local_llmgrader.php
|
+-- tests/
|   +-- observer_test.php
|   +-- essay_grader_test.php
|   +-- llm_client_test.php
|   +-- response_parser_test.php
|   +-- retry_manager_test.php
|   +-- content_converter_test.php
|
+-- docs/
    +-- architecture.md
    +-- implementation-log.md
    +-- test-plan.md
    +-- decisions.md


The actual structure may be adjusted after inspecting Moodle 5.0 source code.

Moodle API compatibility takes precedence over this proposed layout.

5. Component responsibilities
5.1 Event Observer

File:

classes/observer.php

Responsibilities:

Listen for the verified Moodle quiz submission event.
Identify the submitted attempt.
Verify that the attempt is in the appropriate submitted state.
Inspect its question slots.
Identify eligible Essay questions.
Create asynchronous grading tasks.

The observer must not:

Execute HTTP requests.
Wait for LLM responses.
Perform actual grading.
Modify Question Engine records directly.

Event selection and event payload handling must be verified against the installed Moodle 5.0.x source.

5.2 Question Locator

File:

classes/local/question_locator.php

Responsibilities:

Retrieve the question usage associated with the submitted attempt.
Enumerate question slots.
Resolve the actual question definition associated with each slot.
Identify Essay question types.
Retrieve the actual question version used in the attempt.
Retrieve graderinfo.
Retrieve maximum marks.
Retrieve question text and relevant context.
Retrieve the student's submitted response.

Eligibility must be determined using the question version associated with the attempt.

The current question-bank version must never replace the historical version used by the submitted attempt.

Only Essay questions with non-empty grading instructions are eligible.

5.3 Ad hoc Tasks

Files:

classes/task/grade_attempt.php
classes/task/grade_essay.php

Responsibilities:

Execute grading outside the quiz submission request.
Retrieve the necessary question and attempt information.
Verify grading state before processing.
Coordinate the grading operation.
Handle failures and retries.
Ensure that duplicate task execution does not cause duplicate grading.

Tasks must be safe to execute more than once.

Task payloads should contain only the identifiers needed to retrieve the relevant Moodle data.

Student responses should not be unnecessarily duplicated in task payloads.

5.4 Essay Grader

File:

classes/local/essay_grader.php

This component orchestrates the complete grading operation.

Responsibilities:

Verify task eligibility.
Acquire the appropriate locks.
Verify the current grading state.
Retrieve the question and response.
Convert Moodle content to Markdown.
Build the evaluation prompt.
Invoke the LLM client.
Parse the returned JSON.
Validate the evaluation.
Convert feedback Markdown into safe HTML.
Verify that the question has not been manually graded or modified.
Commit the grade through Moodle Question Engine APIs.
Record the result.

The grader must not assume that an LLM response is correct simply because it contains valid JSON.

5.5 Content Converter

Files:

classes/local/content_converter.php
classes/local/html_to_markdown.php
classes/local/markdown_to_html.php

This subsystem is a core architectural component.

It provides bidirectional conversion between Moodle HTML and LLM-friendly Markdown.

HTML to Markdown

Convert:

Question text.
Question context.
Student response.
Grading instructions.

Respect Moodle's original content format.

Plain text must not be interpreted as HTML without explicit format information.

Preserve semantic information, including:

Headings.
Paragraphs.
Lists and nested lists.
Tables.
Links.
Bold and italic formatting.
Code.
Mathematical notation.
Meaningful line breaks.

Remove irrelevant presentation markup and unsafe content.

Markdown to HTML

Convert LLM feedback into Moodle-compatible HTML.

The Markdown parser must disable raw HTML processing.

The resulting HTML must be sanitized using Moodle security APIs.

Only appropriate semantic HTML elements should be allowed.

The converter must not permit:

JavaScript.
Event handlers.
Iframes.
Embedded objects.
Unsafe URLs.
Arbitrary HTML attributes.

The resulting feedback must use the correct Moodle format metadata.

5.6 Prompt Builder

File:

classes/local/prompt_builder.php

Responsibilities:

Construct the system instructions.
Separate teacher-provided instructions from student-provided content.
Define the expected JSON response schema.
Include the actual maximum available mark.
Specify the feedback language.
Prevent student-provided instructions from changing grading criteria.

Prompt structure:

text
SYSTEM INSTRUCTIONS

QUESTION
...

QUESTION CONTEXT
...

GRADING RUBRIC
...

STUDENT RESPONSE
...

EVALUATION INSTRUCTIONS
...


Student content must always be treated as untrusted input.

The teacher's grading rubric has precedence over instructions contained in the student response.

5.7 LLM Client

File:

classes/local/llm_client.php

Responsibilities:

Communicate with OpenAI-compatible Chat Completions APIs.
Handle optional Bearer authentication.
Apply configured request parameters.
Enforce HTTP timeouts.
Parse HTTP responses.
Identify temporary and permanent failures.
Return provider responses to the validation layer.

Expected endpoint:

POST /v1/chat/completions

The client must use Moodle-supported HTTP facilities.

API credentials must never appear in logs or user-facing output.

The client must support endpoints that explicitly permit unauthenticated requests.

5.8 Response Parser

File:

classes/local/response_parser.php

Responsibilities:

Parse the model's structured JSON response.
Validate required fields.
Validate numeric values.
Reject NaN and infinite values.
Reject negative scores.
Enforce the actual maximum mark.
Check consistency of maximum marks.
Validate optional criterion scores.
Validate feedback content.

Expected response structure:

json
{
  "score": 8,
  "max_score": 10,
  "comment": "La risposta dimostra una buona comprensione.",
  "criteria": [
    {
      "name": "Comprensione dei concetti",
      "score": 4,
      "max_score": 4,
      "feedback": "Ottima comprensione."
    }
  ]
}


The actual Moodle maximum mark is authoritative.

The model must never be allowed to change the maximum available mark.

5.9 Retry Manager

File:

classes/local/retry_manager.php

Responsibilities:

Classify HTTP and network errors.
Determine whether an error is retryable.
Inspect Retry-After.
Interpret integer delays.
Interpret HTTP-date values.
Apply exponential backoff.
Apply randomized jitter.
Calculate the next execution time.
Enforce the configured retry limit.

Normal exponential backoff:

text
delay = min(max_delay, base_delay * 2^retry_count) + jitter


Provider-mandated delays must never be shortened by configured maximum backoff values.

The retry manager must not use sleep().

Retries must be scheduled through Moodle Ad hoc Tasks.

5.10 Rate Limiter

File:

classes/local/rate_limiter.php

Responsibilities:

Enforce provider-level concurrency restrictions.
Coordinate concurrent grading tasks.
Avoid excessive parallel requests.
Use Moodle locking mechanisms where appropriate.

Concurrency restrictions must remain effective when tasks are rescheduled.

5.11 Grading Repository

File:

classes/local/grading_repository.php

Responsibilities:

Persist grading operation state.
Identify individual grading operations.
Prevent duplicate operations.
Store execution metadata.
Store retry state.
Record errors and completion status.
Provide information for the administration interface.

The repository must use Moodle's database abstraction.

Question Engine grading data must never be modified directly.

6. Grading lifecycle and state management

Each grading operation must have a deterministic identity based on:

Quiz attempt ID.
Question slot.
Question ID.
Question version.

The repository should enforce uniqueness for this identity.

Suggested states:

State	Description
pending	Grading operation created
processing	Task currently executing
retry_scheduled	Temporary failure; retry scheduled
completed	Grade successfully recorded
failed	Permanent failure or retry exhaustion
skipped	Question is no longer eligible
manual_review	Manual intervention required

State transitions must be validated.

Example:

text
pending
   |
   v
processing
   |
   +---- completed
   |
   +---- retry_scheduled
   |          |
   |          v
   |       processing
   |
   +---- failed
   |
   +---- manual_review


A completed grading operation must not be executed again automatically.

A teacher's manual modification must not be overwritten by a subsequent automatic task.

7. Moodle Question Engine integration

This is the most sensitive integration point.

The plugin must inspect the installed Moodle source code before implementation.

The following must be verified:

Quiz submission event.
Quiz attempt lifecycle.
Question usage retrieval.
Question slot resolution.
Question version handling.
Essay question identification.
Student response retrieval.
Graderinfo retrieval.
Maximum marks.
Question Engine manual grading APIs.
Feedback storage format.
Quiz grade recalculation.
Gradebook synchronization.

The preferred grading mechanism is Moodle's Question Engine API, including manual_grade() when verified as appropriate.

No assumptions should be made about method signatures or grading behavior.

Before committing a grade:

Verify the attempt and slot.
Verify that the response is still eligible.
Verify that no manual grade has superseded the automatic result.
Verify that the question attempt is in a valid state for grading.
Apply the grade through the verified Moodle API.
Verify the resulting question grade.
Verify quiz grade synchronization.

Teacher modifications must always take precedence over automated grading.

8. Database architecture

The plugin should maintain its own XMLDB-managed grading log.

Suggested table:

local_llmgrader_log

Fields:

Field	Purpose
id	Primary key
attemptid	Quiz attempt identifier
slot	Question slot
questionid	Question identifier
questionversion	Actual question version
status	Grading operation state
model	LLM model identifier
score	Assigned mark
maxscore	Maximum mark
feedback	Sanitized feedback
retrycount	Number of attempts
nextretry	Next scheduled retry
lasthttpcode	Last HTTP status
lastretryreason	Sanitized diagnostic reason
timecreated	Creation timestamp
timemodified	Last modification timestamp
timecompleted	Completion timestamp
error	Sanitized error description

Additional requirements:

Appropriate indexes.
Unique grading-operation identity.
XMLDB schema definitions.
Upgrade support.
Data retention.
Privacy-aware deletion.
No API credential storage.
No unnecessary copies of student responses.

The exact schema must be finalized after reviewing Moodle's database conventions.

9. Provider configuration

Settings are managed through Moodle administration:

Site administration > Plugins > Local plugins > LLM Grader.

Configuration includes:

Plugin enabled/disabled.
API endpoint.
Optional API key.
Model.
Temperature.
Maximum output tokens.
HTTP timeout.
System prompt.
Feedback language.
Retry enablement.
Maximum attempts.
Base retry delay.
Maximum normal backoff.
Maximum concurrent requests.

The connection test must use synthetic test content.

It must not transmit real student responses.

10. Security architecture

Security controls apply to every processing stage.

Input security
Validate Moodle identifiers.
Validate provider configuration.
Validate HTTP parameters.
Validate JSON responses.
Validate numeric marks.
Validate URLs and endpoint configuration.
Output security
Escape administrative output.
Sanitize generated feedback.
Restrict allowed HTML.
Prevent unsafe URLs.
Never trust model-generated markup.
Access control
Define appropriate Moodle capabilities.
Protect administration pages.
Require sesskey validation for state-changing requests.
Restrict retry and manual re-evaluation operations to authorized staff.
Prompt injection

Student content must be treated as untrusted data.

The LLM must follow teacher-defined grading instructions and must not accept student instructions that attempt to:

Change grading criteria.
Increase maximum marks.
Alter output format.
Override system instructions.
Request disclosure of hidden prompts.
Credential security

API credentials must be stored using appropriate Moodle configuration mechanisms.

Credentials must never be exposed through:

Browser output.
Student feedback.
Exception messages.
Debug logs.
Grading history.
11. Privacy architecture

Implement Moodle Privacy API support.

The plugin must document:

What information it stores.
Why the information is stored.
Which information is sent to the external provider.
How long grading records are retained.
How personal data can be exported.
How personal data can be deleted.

Student names, email addresses, usernames, and other unnecessary identifiers must not be sent to the provider.

The complete student response is transmitted only when required for grading.

External processing must be explicitly documented for Moodle administrators.

12. Administration interface

The administration interface should provide:

General settings
Plugin activation.
Provider endpoint.
Model.
Authentication.
Prompt configuration.
Feedback language.
Retry and concurrency
Retry enablement.
Maximum attempts.
Backoff configuration.
Provider concurrency limit.
Connection testing
Synthetic provider request.
Connection status.
Model response validation.
Sanitized diagnostics.
Grading activity
Recent grading operations.
Completed evaluations.
Pending operations.
Scheduled retries.
Failed operations.
Manual review requirements.
Manual recovery

Authorized staff should be able to request another evaluation after reviewing a failed operation.

Re-evaluation must not overwrite an existing teacher grade.

13. Testing architecture

Testing must cover each independent subsystem and the complete Moodle lifecycle.

Unit tests
Question eligibility.
Question version resolution.
HTML-to-Markdown conversion.
Markdown-to-HTML conversion.
Prompt construction.
JSON response validation.
Decimal score handling.
HTTP response classification.
Retry calculations.
Retry-After parsing.
Idempotency.
Concurrency control.
Integration tests
Quiz submission event.
Ad hoc Task creation.
Background execution.
Question Engine integration.
Grade registration.
Quiz grade recalculation.
Gradebook synchronization.
Retry scheduling.
Manual grade preservation.
Multiple attempts.
Question version differences.

All external LLM requests must be mocked during automated tests.

Real provider tests require explicit authorization.

14. Operational requirements

Moodle Cron must be configured and operational.

Ad hoc Tasks must be executable.

The plugin must tolerate:

Provider outages.
HTTP timeouts.
Rate limits.
Duplicate task execution.
Interrupted PHP processes.
Multiple concurrent quiz submissions.
Moodle upgrades.
Temporary database failures.

A failure in the external provider must never prevent a student from submitting a quiz.

Failed automatic grading must leave the question available for manual teacher assessment.

15. Implementation strategy

Development must follow these phases.

Phase A — Environment discovery

Inspect:

Moodle installation.
Moodle version and build.
PHP version.
Database.
Web server.
Moodle CLI.
PHPUnit.
Cron.
Ad hoc Tasks.
Phase B — Moodle API investigation

Inspect actual Moodle 5.0 source code.

Document all relevant APIs and method signatures.

Do not implement Moodle-specific integrations based on assumptions.

Phase C — Architecture validation

Finalize:

Event handling.
Question Engine integration.
Database schema.
Task lifecycle.
Conversion strategy.
Retry scheduling.
Concurrency strategy.
Privacy model.
Phase D — Implementation

Implement the plugin incrementally.

Maintain documentation and implementation logs.

Phase E — Testing

Run unit tests, integration tests, installation tests, and security checks.

Document actual results.

Phase F — Delivery

Produce the complete plugin source, documentation, test reports, and installation ZIP.

16. Acceptance criteria

The architecture is considered successfully implemented when:

The plugin installs on Moodle 5.0.x.
Moodle core remains untouched.
Quiz submissions are not blocked by LLM requests.
Only eligible Essay questions are automatically graded.
The actual question version used in the attempt is respected.
HTML and Markdown conversions preserve meaningful content.
Plain-text responses are handled correctly.
LLM responses are strictly validated.
Feedback is safely stored using Moodle-supported APIs.
Teacher grades and modifications are preserved.
Quiz and gradebook grades remain consistent.
HTTP 429 and Retry-After are correctly handled.
Temporary errors are retried asynchronously.
Permanent errors are not retried unnecessarily.
Duplicate grading is prevented.
Provider concurrency restrictions are respected.
Privacy and security requirements are implemented.
Automated and integration tests are executed.
All known limitations are documented.
The final ZIP archive is produced.

Final archive:

local_llmgrader_moodle50.zip

The archive must contain the plugin directory llmgrader/ at its root.

17. Critical development instruction

This document describes the intended architecture, not verified Moodle API behavior.

The actual implementation must always be based on inspection of the supplied Moodle 5.0.x source code.

When an architectural assumption conflicts with the actual Moodle implementation, investigate the discrepancy and document the resulting decision in:

docs/architecture.md
docs/decisions.md
docs/implementation-log.md

Never invent API behavior.

Never bypass Moodle's Question Engine.

Never compromise grading correctness to simplify implementation.

Never claim functionality or test results that have not been verified.