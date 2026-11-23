# Test plan

Internal test documentation for `local_llmgrader`. Results are updated only
after the corresponding command has actually been executed.

## Environment

| Item | Value |
|---|---|
| Moodle | 5.0.12 (Build 20261112) |
| PHP | 8.2.4 |
| Database | MariaDB 10.4.28 |
| OS | Windows (Moodle Windows Installer) |
| Moodle root | `C:\MoodleWindowsInstaller-latest-500\server\moodle` |
| Plugin path | `local/llmgrader` |

## PHPUnit availability

The supplied Moodle Windows Installer does not ship the development dependencies
(`vendor/`, `phpunit`). PHPUnit was therefore not executed; the automated
coverage comes from the CLI scripts `test_parser.php`,
`test_markdown_normalise.php` and `test_converter.php`, which exercise the same
classes through the installed Moodle configuration. The plugin ships PHPUnit
tests under `tests/` that can be run once `composer install --dev` has been
executed in the Moodle root.

| ID | Check | Command | Expected | Status |
|---|---|---|---|---|
| S1 | PHP syntax, all plugin files | `php -l` on every `*.php` | No syntax errors | Pending final run |
| S2 | Moodle coding style (optional) | `phpcs --standard=moodle` | No errors | Not executed |
| S3 | Language files valid UTF-8 | byte check with `mb_check_encoding` | Valid UTF-8, no BOM | Passed |

## 2. Content conversion

| ID | Case | Expected | Status |
|---|---|---|---|
| C1 | Italian accents HTML → Markdown | `è`, `à`, `é` preserved, no `Ã¨` | Passed |
| C2 | Euro sign and typographic quotes | `€`, `“ ”` preserved | Passed |
| C3 | Nested lists | Indentation preserved | Passed (manual) |
| C4 | Tables with header row | Markdown pipe table with contiguous rows | Passed |
| C5 | Script/style/hidden content | Removed | Passed (manual) |
| C6 | Markdown → HTML with table | Real `<table>` output | Passed |
| C7 | Raw HTML from model | Not interpreted, sanitised | Passed (manual) |
| C8 | `javascript:` link | Removed by HTMLPurifier | Passed (manual) |

## 3. Response parser

Automated script: `test_parser.php` (CLI, uses the installed Moodle config).

| ID | Case | Expected | Status |
|---|---|---|---|
| P1 | Weighted criteria, max mark 10 | 7.23 | Passed |
| P2 | Fenced JSON, comma decimals, `%` weights | 0.74 | Passed |
| P3 | Legacy flat `{score, comment}` | Accepted | Passed |
| P4 | Missing `max_score` | `moodle_exception` | Passed |
| P5 | Invalid JSON | `moodle_exception` | Passed |
| P6 | Missing weights | Equal weights | Passed |
| P7 | Truncated JSON | Complete criteria kept, warning added | Passed |
| P8 | Literal newline inside string | Repaired, text preserved | Passed |
| P9 | Trailing commas | Repaired | Passed |
| P10 | UTF-8 in feedback | Accents preserved | Passed |

## 4. End-to-end with mock OpenAI server

Mock server: `mock_openai.php` on `http://127.0.0.1:8899/v1`.

| ID | Check | Expected | Status |
|---|---|---|---|
| M1 | Observer queues ad hoc task | One `grade_attempt` task | Passed |
| M2 | Task completes | Log `status=completed` | Passed |
| M3 | Per-criterion score | 0.72/1 for the mock rubric | Passed |
| M4 | HTTP status recorded | 200 | Passed |
| M5 | Feedback HTML | Table, per-criterion text, overall comment | Passed |
| M6 | `quiz_attempts.sumgrades` | 0.72 | Passed |
| M7 | `quiz_grades.grade` | 7.2 (quiz max 10) | Passed |
| M8 | `grade_grades.finalgrade` | 7.2 | Passed |
| M9 | Question state | `mangrpartial` | Passed |
| M10 | Prompt UTF-8 | `è` present, no `Ã¨` | Passed |
| M11 | Prompt delimiters | Question, rubric, response blocks with nonce | Passed |
| M12 | Contract and security sections | Present in system message | Passed |

## 5. End-to-end with the real local LLM

Endpoint: `http://127.0.0.1:8888/v1`, model
`HauhauCS/Gemma-4-E4B-Uncensored-HauhauCS-Aggressive:Q6_K_P`,
`http_timeout=600`, `max_tokens=4096`.

| ID | Check | Expected | Status |
|---|---|---|---|
| R1 | HTTP request completes | 200 within 600 s | Passed (200, 107 s) |
| R2 | JSON valid after tolerant parsing | Criteria parsed | Passed (5 criteria) |
| R3 | Weighted score matches criteria | Recomputed manually | Passed (0.31 = 31/100) |
| R4 | Feedback stored and rendered | HTML table, no mojibake | Passed (UTF-8 valid, no `Ã`) |
| R5 | Grade propagated | `sumgrades`, `quiz_grades`, `grade_grades` | Passed (0.31 / 3.1 / 3.1, `mangrpartial`) |

### Findings from the real-LLM runs

- The first attempt used `max_tokens=2048` and the model produced an
  incomplete JSON object (`"max_score": 5` followed by a syntax error). The
  parser now recovers complete criteria from truncated JSON and adds a visible
  warning to the feedback.
- With `max_tokens=4096` the model returned a complete 5-criterion JSON object.
- The default `max_tokens` was raised to 4096 and the default HTTP timeout to
  300 s; slow local models may still need 600 s or more.
- Markdown lists emitted as `**Title:**\n* item` were rendered as literal
  asterisks. The converter now inserts the blank line Markdown requires before
  a list, without touching fenced code blocks.

## 6. Security and permissions

| ID | Check | Expected | Status |
|---|---|---|---|
| X1 | Capability checks on config pages | `moodle/site:config` / `moodle/course:manageactivities` | Passed (manual) |
| X2 | `sesskey` on state-changing requests | Required | Passed (manual) |
| X3 | API key not shown in logs | Absent | Passed (inspection) |
| X4 | Local endpoint blocked unless opted in | `endpointblocked` | Passed (manual) |
| X5 | Prompt injection attempt in student response | Treated as data | Passed (prompt inspection) |

## 7. Install / upgrade

| ID | Check | Expected | Status |
|---|---|---|---|
| U1 | Fresh install creates tables | Course/quiz config + log tables | Passed |
| U2 | Upgrade from 1.2.0 to 1.3.0 | Savepoint reached, no schema error | Passed (2026112300) |
| U3 | ZIP structure | Single `llmgrader/` root with `version.php` | Passed (38 entries, forward slashes) |
