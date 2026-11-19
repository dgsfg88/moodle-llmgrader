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
 * LLM client for OpenAI-compatible Chat Completions API.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\local;

// MOODLE_INTERNAL check removed - class is autoloaded

/**
 * LLM client class.
 *
 * Communicates with OpenAI-compatible Chat Completions APIs.
 */
class llm_client {

    /** @var string API endpoint */
    protected string $endpoint;

    /** @var string|null API key */
    protected ?string $apikey;

    /** @var string Model name */
    protected string $model;

    /** @var float Temperature */
    protected float $temperature;

    /** @var int Max output tokens */
    protected int $maxtokens;

    /** @var int HTTP timeout in seconds */
    protected int $timeout;

    /** @var string Last used model (for reporting) */
    protected ?string $lastmodel = null;

    /**
     * Constructor.
     *
     * @param object|null $config Optional configuration object. If null, uses global config.
     */
    public function __construct(?object $config = null) {
        if ($config) {
            $this->endpoint = $config->api_endpoint ?: '';
            $this->apikey = $config->api_key ?: null;
            $this->model = $config->model ?: 'gpt-4o';
            $this->temperature = (float)($config->temperature ?? 0.2);
            $this->maxtokens = (int)($config->max_tokens ?? 2048);
            $this->timeout = (int)($config->http_timeout ?? 120);
        } else {
            $this->endpoint = get_config('local_llmgrader', 'api_endpoint') ?: '';
            $this->apikey = get_config('local_llmgrader', 'api_key') ?: null;
            $this->model = get_config('local_llmgrader', 'model') ?: 'gpt-4o';
            $this->temperature = (float)(get_config('local_llmgrader', 'temperature') ?: 0.2);
            $this->maxtokens = (int)(get_config('local_llmgrader', 'max_tokens') ?: 2048);
            $this->timeout = (int)(get_config('local_llmgrader', 'http_timeout') ?: 120);
        }
    }

    /**
     * Send a grading request to the LLM provider.
     *
     * @param array $context The grading context built by essay_grader.
     * @return string The raw response text from the LLM.
     * @throws \Exception On HTTP failure or invalid response.
     */
    public function send_request(array $context): string {
        if (empty($this->endpoint)) {
            throw new \moodle_exception('noapiendpoint', 'local_llmgrader');
        }

        // Build the prompt using the prompt builder.
        $promptbuilder = new prompt_builder();
        $messages = $promptbuilder->build_messages($context);

        // Build the request payload.
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'response_format' => ['type' => 'json_object'],
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxtokens,
        ];

        // Build HTTP headers.
        $headers = [
            'Content-Type: application/json',
        ];
        if ($this->apikey) {
            $headers[] = 'Authorization: Bearer ' . $this->apikey;
        }

        // Send the HTTP request using Moodle's HTTP client.
        $http = new \core\http\Client();
        $options = [
            \core\http\Client::OPTION_TIMEOUT => $this->timeout,
            \core\http\Client::OPTION_CONNECTTIMEOUT => 30,
        ];

        try {
            $response = $http->post(
                $this->endpoint,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                [
                    'headers' => $headers,
                    'options' => $options,
                ]
            );
        } catch (\moodle_exception $e) {
            throw new \moodle_exception('httppostfailed', 'local_llmgrader', '', $e->getMessage());
        }

        $httpcode = $response->get_status_code();
        $body = $response->get_body();

        // Log HTTP code (without exposing API key).
        $this->lastmodel = $this->model;

        if ($httpcode >= 400 && $httpcode < 500 && $httpcode !== 429) {
            throw new \moodle_exception('llmrequestfailed', 'local_llmgrader', '', "HTTP {$httpcode}: " . substr($body, 0, 500));
        }

        if ($httpcode >= 500) {
            throw new \moodle_exception('llmserviceunavailable', 'local_llmgrader', '', "HTTP {$httpcode}");
        }

        if ($httpcode === 429) {
            throw new \moodle_exception('llmratelimited', 'local_llmgrader', '', "HTTP {$httpcode}");
        }

        // Parse the JSON response.
        $data = @json_decode($body, true);
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            throw new \moodle_exception('invalidllmresponse', 'local_llmgrader', '', substr($body, 0, 500));
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * Get the model name used for the last request.
     *
     * @return string|null
     */
    public function get_model(): ?string {
        return $this->lastmodel;
    }
}
