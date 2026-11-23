<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of GNU General Public License as published by
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
 * English language strings for LLM Grader plugin.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General strings.
$string['pluginname'] = 'LLM Grader';
$string['llmgrader:configure'] = 'Configure LLM Grader';
$string['llmgrader:viewlog'] = 'View grading logs';
$string['llmgrader:manualregrade'] = 'Request manual regrading';
$string['manualregrade'] = 'LLM re-grading';
$string['retryquestion'] = 'Re-grade';
$string['regradequeued'] = 'LLM grading has been re-queued. The result will be available shortly.';
$string['regradefailed'] = 'Could not re-queue grading: no log found for this question.';
$string['nologs'] = 'No LLM grading records found for this attempt.';
$string['status'] = 'Status';
$string['score'] = 'Score';
$string['llmgrader:configurecourse'] = 'Configure LLM Grader at course level';
$string['llmgrader:configurequiz'] = 'Configure LLM Grader at quiz level';
$string['llmgrader:viewconfiguration'] = 'View LLM Grader configuration';

// Settings.
$string['settingsheading'] = 'LLM Grader Settings';
$string['generalheading'] = 'General Settings';
$string['enabled'] = 'Enable LLM Grader';
$string['enabled_desc'] = 'Activate automatic essay question grading via LLM.';
$string['apiendpoint'] = 'API Endpoint';
$string['apiendpoint_desc'] = 'URL of the OpenAI-compatible Chat Completions API endpoint (e.g., https://api.openai.com/v1/chat/completions).';
$string['apikey'] = 'API Key (optional)';
$string['apikey_desc'] = 'Authentication key for the LLM API. Leave empty to send requests without Bearer authentication.';
$string['model'] = 'Model';
$string['model_desc'] = 'Name of the LLM model to use (e.g., gpt-4o, claude-3-sonnet).';
$string['temperature'] = 'Temperature';
$string['temperature_desc'] = 'Temperature value for generation (0.0-2.0). Lower values produce more deterministic results.';
$string['maxtokens'] = 'Max output tokens';
$string['maxtokens_desc'] = 'Maximum number of tokens to generate in the response. Rubrics with several criteria need at least 2048-4096 tokens.';
$string['http_timeout'] = 'HTTP timeout (seconds)';
$string['http_timeout_desc'] = 'Timeout in seconds for HTTP requests to the LLM provider. Slow local models may need 600 seconds or more.';
$string['allowlocalendpoints'] = 'Allow endpoints on private/local networks';
$string['allowlocalendpoints_desc'] = 'Allow requests to LLM endpoints hosted on localhost or private IP ranges. Moodle blocks these by default to prevent SSRF attacks. Enable this only if you control the configured endpoint, for example a local LLM server.';

// Prompt settings.
$string['promptheading'] = 'Prompt Configuration';
$string['systemprompt'] = 'System Prompt';
$string['systemprompt_desc'] = 'System instructions for the LLM model. Leave empty to use a default English prompt.';
$string['feedbacklanguage'] = 'Feedback language';
$string['feedbacklanguage_desc'] = 'Default language for student feedback (e.g., it, en).';

// Retry settings.
$string['retryheading'] = 'Retry Settings';
$string['retryenabled'] = 'Enable automatic retry';
$string['retryenabled_desc'] = 'Automatically retry temporarily failed requests.';
$string['maxretries'] = 'Maximum retry count';
$string['maxretries_desc'] = 'Maximum number of attempts before reporting a permanent failure.';
$string['baseretrydelay'] = 'Base retry delay (seconds)';
$string['baseretrydelay_desc'] = 'Initial delay for exponential backoff.';
$string['maxbackoffdelay'] = 'Max backoff delay (seconds)';
$string['maxbackoffdelay_desc'] = 'Maximum delay for exponential backoff (seconds).';

// Help strings (used by addHelpButton).
$string['enabled_help'] = 'Activate automatic essay question grading via LLM.';
$string['apiendpoint_help'] = 'URL of the OpenAI-compatible Chat Completions API endpoint (e.g., https://api.openai.com/v1/chat/completions).';
$string['apikey_help'] = 'Authentication key for the LLM API. Leave empty to send requests without Bearer authentication.';
$string['model_help'] = 'Name of the LLM model to use (e.g., gpt-4o, claude-3-sonnet).';
$string['temperature_help'] = 'Temperature value for generation (0.0-2.0). Lower values produce more deterministic results.';
$string['maxtokens_help'] = 'Maximum number of tokens to generate in the response. Rubrics with several criteria need at least 2048-4096 tokens.';
$string['http_timeout_help'] = 'Timeout in seconds for HTTP requests. Slow local models may need 600 seconds or more.';
$string['systemprompt_help'] = 'System instructions for the LLM model. If left empty, a default Italian prompt will be used.';
$string['feedbacklanguage_help'] = 'Default language for student feedback (e.g., it, en).';
$string['maxretries_help'] = 'Maximum number of attempts before reporting a permanent failure.';
$string['baseretrydelay_help'] = 'Initial delay for exponential backoff.';
$string['maxbackoffdelay_help'] = 'Maximum delay for exponential backoff (seconds).';

// Concurrency.
$string['concurrencyheading'] = 'Concurrency Settings';
$string['maxconcurrent'] = 'Maximum concurrent requests';
$string['maxconcurrent_desc'] = 'Maximum number of simultaneous LLM requests.';

// Multi-level configuration.
$string['multilevelheading'] = 'Multi-level Configuration';
$string['multilevel_desc'] = 'LLM Grader supports configuring LLM settings at three levels: global (site), course, and quiz. Settings inherit from the lowest to highest level (quiz > course > global). An empty field inherits the value from the lower level.';
$string['configurecourses'] = 'Configure courses';
$string['courseconfigheading'] = 'LLM Grader Configuration - Course';
$string['courseconfigdesc'] = 'Configure LLM settings specific to this course. Empty settings inherit from global values.';
$string['courseconfigreadonly'] = 'You do not have permission to modify this course configuration.';
$string['quizconfigheading'] = 'LLM Grader Configuration - Quiz';
$string['quizconfigdesc'] = 'Configure LLM settings specific to this quiz. Empty settings inherit from the course or global values.';
$string['quizconfigreadonly'] = 'You do not have permission to modify this quiz configuration.';
$string['quizconfignote'] = 'Quiz-level configuration is accessible from the quiz editing page (under "LLM Grader Configuration" section).';
$string['inheritanceinfo'] = 'Configuration inheritance information';
$string['configlevel'] = 'Configuration level';
$string['configlevelglobal'] = 'Global (site)';
$string['configlevelcourse'] = 'Course';
$string['configlevelquiz'] = 'Quiz';
$string['overridden_by_course'] = 'Course override';
$string['overridden_by_quiz'] = 'Quiz override';
$string['saveconfig'] = 'Save configuration';
$string['resettoinherit'] = 'Reset to global inheritance';
$string['configsaved'] = 'Configuration saved successfully.';
$string['configreset'] = 'Configuration reset. Global values will be used.';

// Connection test.
$string['testconnection'] = 'Test connection';
$string['testconnectionbutton'] = 'Test API connection';
$string['connectiontestsuccess'] = 'API connection verified successfully. Model: {$a}';
$string['connectiontestfailed'] = 'Connection test failed: {$a}';

// Log and activity.
$string['logheading'] = 'Recent grading activity';
$string['nogradesfound'] = 'No grades found.';
$string['statuscompleted'] = 'Completed';
$string['statuspending'] = 'Pending';
$string['statusprocessing'] = 'Processing';
$string['statusretry_scheduled'] = 'Retry scheduled';
$string['statusfailed'] = 'Failed';
$string['statusskipped'] = 'Skipped';
$string['statusmanual_review'] = 'Manual review';
$string['gradinglogs'] = 'Grading logs';

// Error strings.
$string['noapiendpoint'] = 'API endpoint not configured. Contact the site administrator.';
$string['httppostfailed'] = 'HTTP request error to LLM provider: {$a}';
$string['llmrequestfailed'] = 'LLM request failed: {$a}';
$string['llmserviceunavailable'] = 'LLM service unavailable (server error).';
$string['llmratelimited'] = 'Rate limit reached. Please try again later.';
$string['invalidllmresponse'] = 'Invalid LLM response: {$a}';
$string['endpointblocked'] = 'The configured LLM endpoint is blocked by Moodle\'s HTTP security policy: {$a}. Enable "Allow endpoints on private/local networks" in the plugin settings, or add the host to the core HTTP security allow list.';
$string['invalidjson'] = 'Invalid JSON in LLM response: {$a}';
$string['missing_criteria'] = 'LLM response does not contain a valid "criteria" array.';
$string['invalid_criterion'] = 'Invalid criterion in the LLM response: {$a}';
$string['missing_score'] = 'LLM response does not contain the "score" field.';
$string['missing_comment'] = 'LLM response does not contain the "comment" field.';
$string['invalid_score'] = 'Score returned by the model is not a valid number.';
$string['negative_score'] = 'Score returned by the model is negative.';
$string['empty_comment'] = 'Comment returned by the model is empty.';
$string['nopermissions'] = 'Insufficient permissions to perform this operation.';
$string['error_invalid_url'] = 'The entered URL is not valid.';
$string['error_temperature_range'] = 'Temperature must be between 0.0 and 2.0.';
$string['error_quiz_mismatch'] = 'Validation error: the quiz does not belong to the specified course.';

// Privacy.
$string['privacy:metadata:llmgrader_log'] = 'The LLM Grader plugin stores a log of grading operations to track automatic grading status.';
$string['privacy:metadata:log:attemptid'] = 'ID of the graded quiz attempt.';
$string['privacy:metadata:log:slot'] = 'Question slot number.';
$string['privacy:metadata:log:questionid'] = 'ID of the graded question.';
$string['privacy:metadata:log:status'] = 'Status of the grading operation.';
$string['privacy:metadata:log:score'] = 'Score assigned by automatic grading.';
$string['privacy:metadata:log:maxscore'] = 'Maximum score of the question.';
$string['privacy:metadata:log:feedback'] = 'Feedback generated by the LLM model.';
$string['privacy:metadata:log:retrycount'] = 'Number of grading attempts.';
$string['privacy:metadata:log:timecreated'] = 'Timestamp of record creation.';
$string['privacy:metadata:log:timemodified'] = 'Timestamp of last modification.';
$string['privacy:metadata:log:timecompleted'] = 'Completion timestamp.';
$string['privacy:metadata:log:configlevel'] = 'Level of configuration used (global, course, quiz).';
$string['privacy:metadata:log:configid'] = 'ID of the configuration record used.';
$string['privacy:metadata:llm_provider'] = 'Student response data is sent to an external LLM provider for automatic grading.';
$string['privacy:metadata:external:purpose'] = 'External data processing for automated essay grading using Artificial Intelligence.';
$string['privacy:metadata:course_config'] = 'LLM Grader configuration specific to the course, overriding global settings.';
$string['privacy:metadata:course_config:courseid'] = 'ID of the course for which LLM grading was configured.';
$string['privacy:metadata:course_config:enabled'] = 'LLM grading enabled status for the course.';
$string['privacy:metadata:course_config:api_endpoint'] = 'API endpoint configured for the course.';
$string['privacy:metadata:course_config:model'] = 'LLM model configured for the course.';
$string['privacy:metadata:course_config:system_prompt'] = 'System prompt configured for the course.';
$string['privacy:metadata:course_config:feedback_language'] = 'Feedback language configured for the course.';
$string['privacy:metadata:quiz_config'] = 'LLM Grader configuration specific to the quiz, overriding course and global settings.';
$string['privacy:metadata:quiz_config:quizid'] = 'ID of the quiz for which LLM grading was configured.';
$string['privacy:metadata:quiz_config:courseid'] = 'ID of the parent course of the quiz.';
$string['privacy:metadata:quiz_config:enabled'] = 'LLM grading enabled status for the quiz.';
$string['privacy:metadata:quiz_config:api_endpoint'] = 'API endpoint configured for the quiz.';
$string['privacy:metadata:quiz_config:model'] = 'LLM model configured for the quiz.';
$string['privacy:metadata:quiz_config:system_prompt'] = 'System prompt configured for the quiz.';
$string['privacy:metadata:quiz_config:feedback_language'] = 'Feedback language configured for the quiz.';

// Settings (additional).
$string['connectionheading'] = 'Connection Settings';
$string['generationheading'] = 'Generation Settings';
$string['settings'] = 'Settings';
