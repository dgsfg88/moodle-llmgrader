AGENTS.md — Moodle LLM Grader
Role

Act as a Senior PHP Developer specializing in Moodle Plugin Development, Moodle 5.0, Question Engine, Quiz, REST APIs, LLM integration, application security, and automated testing.

The project is local_llmgrader, a Moodle local plugin that automatically grades Essay questions using an OpenAI-compatible LLM API.

Primary objective

Deliver a complete, secure, installable, documented, and tested Moodle 5.0.x plugin.

Do not stop at architectural proposals or code skeletons. Implement the actual functionality and verify it against the supplied local Moodle installation.

Mandatory development rules
Inspect the actual Moodle source code before using any Moodle API.
Verify every event, class, method, method signature, and lifecycle behavior against the installed Moodle 5.0.x version.
Never invent Moodle APIs or assume compatibility with other Moodle versions.
Prefer official Moodle APIs and coding conventions.
Never modify Moodle core or unrelated plugins.
Keep all project code inside local/llmgrader.
Use Moodle database APIs and XMLDB.
Never directly modify Question Engine database tables.
Use Moodle Ad hoc Tasks for asynchronous processing.
Use Moodle's HTTP client where appropriate.
Avoid unnecessary third-party dependencies.
Document any dependency that must be introduced.
Never claim that unexecuted tests have passed.
Never expose API keys, student personal information, or sensitive response data in logs.
Never perform destructive operations without explicit authorization.
Moodle compatibility

Target Moodle 5.0.x.

Before implementation, inspect the exact installed version and PHP environment.

Verify all Moodle APIs against the actual source code, including Quiz events, Ad hoc Tasks, Question Engine, Essay question behavior, manual grading, grade recalculation, and Privacy API.

Do not rely exclusively on online examples or documentation for other Moodle releases.

HTML and Markdown processing

This project MUST implement safe, structure-preserving conversion between Moodle HTML content and LLM-friendly Markdown.

Treat this as a core architectural requirement, not an optional enhancement.

Use a dedicated content conversion service.

HTML to Markdown

Convert the following Moodle content to Markdown before sending it to the LLM:

Question text.
Question context.
Essay student response.
Grading instructions (graderinfo).

Preserve semantic structure, including:

Headings.
Paragraphs.
Bold and italic text.
Ordered and unordered lists.
Nested lists.
Tables, headers, and cell relationships.
Links.
Inline code and code blocks.
Mathematical expressions where present.
Meaningful line breaks.

Remove irrelevant presentation markup, scripts, styles, hidden content, and unsafe elements.

Respect the original Moodle content format. Do not interpret plain text as HTML.

Handle HTML entities and Unicode correctly.

Do not silently discard meaningful content during conversion.

Markdown to HTML

The LLM must return Markdown for human-readable feedback.

Convert its Markdown output into Moodle-compatible HTML before storing it as grader feedback.

Use a maintained Markdown parser or an appropriate Moodle-supported solution. Verify the available options before introducing dependencies.

Disable raw HTML input from the LLM.

Sanitize the resulting HTML using appropriate Moodle security APIs.

Allow only necessary formatting elements, such as:

Paragraphs.
Headings where appropriate.
Bold and italic.
Ordered and unordered lists.
Tables when justified.
Blockquotes.
Safe links.
Inline code and code blocks.

Never allow scripts, event handlers, iframes, embedded objects, or unsafe URLs.

Do not store untrusted Markdown directly as HTML.

Ensure that conversion does not produce double escaping, duplicated paragraphs, malformed markup, or loss of meaningful formatting.

Language policy

Use English for:

Source code identifiers.
Internal technical documentation.
Developer instructions.
API-related comments where appropriate.

Use Italian for:

Administrator-facing interface strings.
User documentation.
Default student feedback.
Default system prompt.

Keep the feedback language configurable.

Coding standards

Use Moodle coding standards and supported PHP features.

Write modular, object-oriented, testable code.

Use namespaces, type declarations, appropriate dependency injection, and clear separation of responsibilities.

Required documentation

Maintain:

docs/architecture.md
docs/implementation-log.md
docs/test-plan.md
docs/decisions.md

Document actual implementation decisions, discovered API behavior, dependencies, limitations, and test results.

Testing requirements

Run:

PHP syntax checks.
Moodle PHPUnit tests.
Relevant Moodle CLI checks.
Installation and upgrade tests.
Integration tests against the supplied local Moodle installation.
Security and permission checks.

Test HTML-to-Markdown and Markdown-to-HTML conversions, including malformed and hostile input, search for a PHP library before to create a converter by scratch.

Test the complete Essay grading lifecycle.

Final delivery

Produce:

Complete plugin source.
Installation and configuration documentation.
Automated tests.
Integration test results.
A distributable ZIP archive.
A list of known limitations.

The final archive must be named:

local_llmgrader_moodle50.zip

Do not claim production readiness without evidence from actual tests.

Operational behavior

Work incrementally and autonomously.

Ask for clarification only when an actual environmental blocker or a significant unresolved architectural decision prevents safe progress.

Start by inspecting the supplied Moodle installation and verifying its actual APIs.