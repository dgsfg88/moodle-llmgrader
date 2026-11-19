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
 * Connection test page for LLM Grader plugin.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->dirroot . '/local/llmgrader/lib.php');

// Check capabilities.
require_login();
require_capability('local/llmgrader:configure', context_system::instance());

// Verify sesskey for POST requests.
if (is_post_request()) {
    require_sesskey();
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/llmgrader/test.php'));
$PAGE->set_title(get_string('pluginname', 'local_llmgrader'));
$PAGE->set_heading(get_string('pluginname', 'local_llmgrader'));

echo $OUTPUT->header();

if (is_post_request()) {
    // Perform a connection test using synthetic content.
    try {
        $endpoint = get_config('local_llmgrader', 'api_endpoint');
        if (empty($endpoint)) {
            throw new moodle_exception('noapiendpoint', 'local_llmgrader');
        }

        $apikey = get_config('local_llmgrader', 'api_key') ?: null;
        $model = get_config('local_llmgrader', 'model') ?: 'gpt-4o';

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => 'Reply with exactly: CONNECTION_TEST_OK']
            ],
            'max_tokens' => 20,
            'temperature' => 0,
        ];

        $headers = ['Content-Type: application/json'];
        if ($apikey) {
            $headers[] = 'Authorization: Bearer ' . $apikey;
        }

        $http = new \core\http\Client();
        $response = $http->post(
            $endpoint,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            [
                'headers' => $headers,
                'options' => [\core\http\Client::OPTION_TIMEOUT => 30],
            ]
        );

        if ($response->get_status_code() === 200) {
            $body = json_decode($response->get_body(), true);
            $reply = $body['choices'][0]['message']['content'] ?? '';
            if (strpos($reply, 'CONNECTION_TEST_OK') !== false) {
                echo $OUTPUT->notification(get_string('connectiontestsuccess', 'local_llmgrader', $model), 'success');
            } else {
                echo $OUTPUT->notification(get_string('connectiontestfailed', 'local_llmgrader', get_string('invalidllmresponse', 'local_llmgrader')), 'warning');
            }
        } else {
            echo $OUTPUT->notification(get_string('connectiontestfailed', 'local_llmgrader', 'HTTP ' . $response->get_status_code()), 'danger');
        }
    } catch (moodle_exception $e) {
        echo $OUTPUT->notification(get_string('connectiontestfailed', 'local_llmgrader', $e->getMessage()), 'danger');
    }
}

// Show recent grading activity.
$stats = local_llmgrader_get_grading_stats();
echo '<h2>' . get_string('logheading', 'local_llmgrader') . '</h2>';
echo '<p>';
echo get_string('statuscompleted', 'local_llmgrader') . ': ' . $stats->completed . ' | ';
echo get_string('statuspending', 'local_llmgrader') . ': ' . $stats->pending . ' | ';
echo get_string('statusfailed', 'local_llmgrader') . ': ' . $stats->failed . ' | ';
echo get_string('statusretry_scheduled', 'local_llmgrader') . ': ' . $stats->retry_scheduled;
echo '</p>';

$recent = local_llmgrader_get_recent_grading(10);
if ($recent) {
    $table = new html_table();
    $table->head = ['ID', 'Attempt', 'Slot', 'Score', 'Status', 'Time'];
    foreach ($recent as $log) {
        $table->data[] = [
            $log->id,
            $log->attemptid,
            $log->slot,
            $log->score ?? '-',
            $log->status,
            userdate($log->timecreated),
        ];
    }
    echo html_table::make($table);
} else {
    echo '<p>' . get_string('nogradesfound', 'local_llmgrader') . '</p>';
}

echo $OUTPUT->footer();
