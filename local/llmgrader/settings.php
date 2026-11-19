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
 * Administration settings for LLM Grader plugin.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // This check is performed for you automatically in admin/settings.php.

    // General settings.
    $settings->add(new admin_setting_configcheckbox(
        'local_llmgrader/enabled',
        new lang_string('enabled', 'local_llmgrader'),
        new lang_string('enabled_desc', 'local_llmgrader'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_llmgrader/api_endpoint',
        new lang_string('apiendpoint', 'local_llmgrader'),
        new lang_string('apiendpoint_desc', 'local_llmgrader'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_llmgrader/api_key',
        new lang_string('apikey', 'local_llmgrader'),
        new lang_string('apikey_desc', 'local_llmgrader'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_llmgrader/model',
        new lang_string('model', 'local_llmgrader'),
        new lang_string('model_desc', 'local_llmgrader'),
        'gpt-4o',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_llmgrader/temperature',
        new lang_string('temperature', 'local_llmgrader'),
        new lang_string('temperature_desc', 'local_llmgrader'),
        '0.2',
        PARAM_FLOAT
    ));

    $settings->add(new admin_setting_configtext(
        'local_llmgrader/max_tokens',
        new lang_string('maxtokens', 'local_llmgrader'),
        new lang_string('maxtokens_desc', 'local_llmgrader'),
        '2048',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_llmgrader/http_timeout',
        new lang_string('http_timeout', 'local_llmgrader'),
        new lang_string('http_timeout_desc', 'local_llmgrader'),
        '120',
        PARAM_INT
    ));

    // Prompt settings.
    $settings->add(new admin_setting_configtextarea(
        'local_llmgrader/system_prompt',
        new lang_string('systemprompt', 'local_llmgrader'),
        new lang_string('systemprompt_desc', 'local_llmgrader'),
        '',
        PARAM_TEXT,
        10,
        60
    ));

    $settings->add(new admin_setting_configtext(
        'local_llmgrader/feedback_language',
        new lang_string('feedbacklanguage', 'local_llmgrader'),
        new lang_string('feedbacklanguage_desc', 'local_llmgrader'),
        'it',
        PARAM_ALPHA
    ));

    // Retry settings.
    $settings->add(new admin_setting_configcheckbox(
        'local_llmgrader/retry_enabled',
        new lang_string('retryenabled', 'local_llmgrader'),
        new lang_string('retryenabled_desc', 'local_llmgrader'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_llmgrader/max_retries',
        new lang_string('maxretries', 'local_llmgrader'),
        new lang_string('maxretries_desc', 'local_llmgrader'),
        '8',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_llmgrader/base_retry_delay',
        new lang_string('baseretrydelay', 'local_llmgrader'),
        new lang_string('baseretrydelay_desc', 'local_llmgrader'),
        '5',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_llmgrader/max_backoff_delay',
        new lang_string('maxbackoffdelay', 'local_llmgrader'),
        new lang_string('maxbackoffdelay_desc', 'local_llmgrader'),
        '900',
        PARAM_INT
    ));

    // Connection test page.
    $settings->add(new admin_setting_description(
        'local_llmgrader/testconnectionheading',
        '',
        '<div style="margin-top: 15px;">' .
        '<form action="' . new moodle_url('/local/llmgrader/test.php') . '" method="post">' .
        '<input type="hidden" name="sesskey" value="' . sesskey() . '">' .
        '<input type="submit" class="btn btn-primary" value="' .
        get_string('testconnectionbutton', 'local_llmgrader') . '">' .
        '</form></div>'
    ));
}
